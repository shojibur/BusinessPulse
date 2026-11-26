<?php

namespace App\Http\Controllers;

use App\Enums\BriefStatus;
use App\Enums\MeetingStatus;
use App\Enums\TaskDraftStatus;
use App\Events\Meetings\MeetingCreated;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Models\Brief;
use App\Models\Department;
use App\Models\Issue;
use App\Models\Meeting;
use App\Services\Meetings\MeetingPresenter;
use App\Services\Tasks\TaskDraftReviewProps;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingPresenter $presenter, private readonly BusinessClock $clock) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Meeting::class);
        $user = $request->user();

        $status = MeetingStatus::tryFrom((string) $request->query('status'));
        $issueId = $request->filled('issue') ? (int) $request->query('issue') : null;

        $meetings = Meeting::query()->visibleTo($user)->with('department:id,name')
            ->withCount(['issues', 'taskDrafts as pending_drafts_count' => fn ($query) => $query->where('status', TaskDraftStatus::Pending->value)])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($issueId !== null, fn ($query) => $query->whereHas('issues', fn ($q) => $q->where('issues.id', $issueId)))
            ->orderByDesc('meeting_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return Inertia::render('meetings/Index', [
            'meetings' => $meetings->getCollection()->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meetingDate' => $meeting->meeting_date->format('Y-m-d'),
                'department' => ['id' => $meeting->department->id, 'name' => $meeting->department->name],
                'status' => $meeting->status->value,
                'statusLabel' => $meeting->status->label(),
                'issueCount' => $meeting->issues_count,
                'pendingDraftCount' => $meeting->pending_drafts_count,
                'url' => route('meetings.show', $meeting),
            ])->values(),
            'pagination' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'total' => $meetings->total(),
                'prev_url' => $meetings->previousPageUrl(),
                'next_url' => $meetings->nextPageUrl(),
            ],
            'filters' => ['status' => $status?->value, 'issue' => $issueId],
            'statusOptions' => array_map(fn (MeetingStatus $s) => ['value' => $s->value, 'label' => $s->label()], MeetingStatus::cases()),
            'issueOptions' => $this->issueOptions($request)->map(fn ($issue) => ['id' => $issue->id, 'title' => $issue->title])->values(),
            'can' => ['create' => true],
        ] + $this->overview($request));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Meeting::class);

        return Inertia::render('meetings/Create', $this->formProps($request, null) + [
            'preselectedIssueId' => $request->filled('issue') ? (int) $request->query('issue') : null,
        ]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $meeting = DB::transaction(function () use ($request): Meeting {
            $meeting = Meeting::query()->create([
                'department_id' => (int) $request->input('department_id'),
                'created_by' => $request->user()->id,
                'title' => trim($request->input('title')),
                'meeting_date' => $request->input('meeting_date'),
                'status' => MeetingStatus::Draft,
            ]);
            $meeting->issues()->sync($request->input('issue_ids', []));
            MeetingCreated::dispatch($meeting, $request->user()->id);

            return $meeting;
        });

        return to_route('meetings.show', $meeting);
    }

    public function show(Request $request, Meeting $meeting): Response
    {
        Gate::authorize('view', $meeting);

        return Inertia::render('meetings/Show', [
            'meeting' => $this->presenter->detail($meeting, $request->user()),
            'transcript' => $meeting->transcript,
            'taskDrafts' => $this->presenter->drafts($meeting),
            'draftReview' => app(TaskDraftReviewProps::class)->for($meeting, $request->user()),
            'limits' => [
                'maxUploadMb' => 20,
                'extensions' => ['mp3', 'm4a', 'wav', 'webm', 'mp4'],
                'intendedMinutes' => 15,
                'transcriptMin' => 20,
                'transcriptMax' => 60000,
            ],
        ] + $this->overview($request));
    }

    public function edit(Request $request, Meeting $meeting): Response
    {
        Gate::authorize('update', $meeting);

        return Inertia::render('meetings/Edit', $this->formProps($request, $meeting));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        DB::transaction(function () use ($request, $meeting): void {
            $meeting->forceFill(['title' => trim($request->input('title')), 'meeting_date' => $request->input('meeting_date')])->save();
            $meeting->issues()->sync($request->input('issue_ids', []));
        });

        return to_route('meetings.show', $meeting);
    }

    /**
     * What the meetings pages share: the meetings the user may see (for the list beside the detail), the latest
     * completed brief the user may see (headline and date only), and today's business date for grouping.
     *
     * @return array<string, mixed>
     */
    private function overview(Request $request): array
    {
        $user = $request->user();
        $timezone = $user->company->timezone;

        $brief = Brief::query()->visibleTo($user)->where('status', BriefStatus::Completed->value)->whereNotNull('headline')
            ->orderByDesc('generated_at')->orderByDesc('id')->first();

        return [
            'sidebar' => Meeting::query()->visibleTo($user)->with('department:id,name')
                ->orderByDesc('meeting_date')->orderByDesc('id')->limit(40)->get()
                ->map(fn (Meeting $meeting) => [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meetingDate' => $meeting->meeting_date->format('Y-m-d'),
                    'departmentName' => $meeting->department->name,
                    'status' => $meeting->status->value,
                    'statusLabel' => $meeting->status->label(),
                    'url' => route('meetings.show', $meeting),
                ])->values(),
            'latestBrief' => $brief === null ? null : [
                'id' => $brief->id,
                'headline' => $brief->headline,
                'generatedAt' => $brief->generated_at?->setTimezone($timezone)->toIso8601String(),
                'url' => route('briefs.show', $brief),
            ],
            'businessToday' => $this->clock->today($timezone)->format('Y-m-d'),
        ];
    }

    /** @return Collection<int, Issue> */
    private function issueOptions(Request $request)
    {
        return Issue::visibleTo($request->user())->orderByDesc('last_detected_at')->limit(200)->get(['id', 'title', 'department_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request, ?Meeting $meeting): array
    {
        $user = $request->user();

        return [
            'meeting' => $meeting === null ? null : [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meetingDate' => $meeting->meeting_date->format('Y-m-d'),
                'departmentId' => $meeting->department_id,
                'issueIds' => $meeting->issues()->pluck('issues.id')->all(),
                'dateLocked' => in_array($meeting->status, [MeetingStatus::Transcribing, MeetingStatus::Extracting], true),
                'hasDrafts' => $meeting->taskDrafts()->exists(),
            ],
            'departments' => Department::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'issueOptions' => $this->issueOptions($request)->map(fn ($issue) => ['id' => $issue->id, 'title' => $issue->title, 'departmentId' => $issue->department_id])->values(),
            'businessToday' => $this->clock->today($user->company->timezone)->format('Y-m-d'),
        ];
    }
}
