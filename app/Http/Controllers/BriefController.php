<?php

namespace App\Http\Controllers;

use App\Enums\AudioStatus;
use App\Enums\BriefSource;
use App\Enums\BriefStatus;
use App\Http\Requests\GenerateBriefRequest;
use App\Http\Requests\RetryBriefRequest;
use App\Jobs\GenerateBriefJob;
use App\Models\Brief;
use App\Models\Company;
use App\Models\Issue;
use App\Services\AI\BriefPresenter;
use App\Services\AI\OpenAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BriefController extends Controller
{
    /** An in-progress brief older than this no longer blocks a new request (the job's unique lock expires too). */
    private const STUCK_AFTER_MINUTES = 15;

    public function __construct(private readonly BriefPresenter $presenter) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Brief::class);
        $user = $request->user();
        $timezone = $user->company->timezone;

        $briefs = Brief::query()->where('company_id', $user->company_id)->latest('created_at')->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('briefs/Index', [
            'briefs' => $briefs->getCollection()->map(fn (Brief $brief) => [
                'id' => $brief->id,
                'status' => $brief->status->value,
                'source' => $brief->source?->value,
                'coverage_status' => $brief->coverage_status?->value,
                'headline' => $brief->headline,
                'created_at' => $brief->created_at->setTimezone($timezone)->toIso8601String(),
                'generated_at' => $brief->generated_at?->setTimezone($timezone)->toIso8601String(),
            ])->values(),
            'pagination' => [
                'current_page' => $briefs->currentPage(),
                'last_page' => $briefs->lastPage(),
                'total' => $briefs->total(),
                'prev_url' => $briefs->previousPageUrl(),
                'next_url' => $briefs->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, Brief $brief): Response
    {
        Gate::authorize('view', $brief);
        $user = $request->user();
        $timezone = $user->company->timezone;
        $brief->load('items');

        $blocks = $this->presenter->blocks($brief);
        $visibleIssueIds = Issue::visibleTo($user)->whereIn('id', $brief->items->pluck('issue_id')->filter())->pluck('id')->all();

        return Inertia::render('briefs/Show', [
            'brief' => [
                'id' => $brief->id,
                'status' => $brief->status->value,
                'source' => $brief->source?->value,
                'coverage_status' => $brief->coverage_status?->value,
                'headline' => $blocks['headline'] ?: null,
                'limitation' => $blocks['limitation'],
                'period_start' => $brief->period_start?->format('Y-m-d'),
                'period_end' => $brief->period_end?->format('Y-m-d'),
                'as_of_date' => $brief->as_of_date?->format('Y-m-d'),
                'created_at' => $brief->created_at->setTimezone($timezone)->toIso8601String(),
                'generated_at' => $brief->generated_at?->setTimezone($timezone)->toIso8601String(),
                'notice' => $this->notice($brief),
                'items' => array_map(fn (array $item) => $item + [
                    'issue_url' => in_array($item['issue_id'], $visibleIssueIds, true) ? route('issues.show', $item['issue_id']) : null,
                ], $blocks['items']),
            ],
            'audio' => [
                'status' => $brief->audio_status->value,
                'configured' => OpenAiClient::speechConfigured(),
                'url' => $brief->audio_status === AudioStatus::Ready && $brief->audio_path !== null ? route('briefs.audio.stream', $brief) : null,
                'can_request' => $brief->status === BriefStatus::Completed && in_array($brief->audio_status, [AudioStatus::None, AudioStatus::Failed], true),
            ],
        ]);
    }

    public function store(GenerateBriefRequest $request): RedirectResponse
    {
        $user = $request->user();

        $brief = DB::transaction(function () use ($user): Brief {
            // Serializes concurrent requests of one company so only one brief is created.
            Company::query()->whereKey($user->company_id)->lockForUpdate()->firstOrFail();

            $inProgress = Brief::query()->where('company_id', $user->company_id)
                ->whereIn('status', [BriefStatus::Queued->value, BriefStatus::Generating->value])
                ->where('updated_at', '>', now()->subMinutes(self::STUCK_AFTER_MINUTES))
                ->exists();

            if ($inProgress) {
                throw ValidationException::withMessages(['brief' => 'A brief is already being generated. Please wait for it to finish.']);
            }

            return Brief::query()->create(['company_id' => $user->company_id, 'requested_by' => $user->id, 'status' => BriefStatus::Queued]);
        });

        GenerateBriefJob::dispatch($brief->id, $brief->company_id);

        return to_route('briefs.show', $brief);
    }

    public function retry(RetryBriefRequest $request, Brief $brief): RedirectResponse
    {
        if ($brief->status !== BriefStatus::Failed) {
            throw ValidationException::withMessages(['brief' => 'Only a failed brief can be retried.']);
        }

        $brief->forceFill(['status' => BriefStatus::Queued, 'error_message' => null])->save();
        GenerateBriefJob::dispatch($brief->id, $brief->company_id);

        return to_route('briefs.show', $brief);
    }

    /** Reader-facing provider notice; never the raw code of an internal failure. */
    private function notice(Brief $brief): ?string
    {
        if ($brief->status === BriefStatus::Failed) {
            return 'The brief could not be generated. You can try again.';
        }

        if ($brief->status !== BriefStatus::Completed) {
            return null;
        }

        if ($brief->source === BriefSource::Ai) {
            return 'AI generated';
        }

        return match (true) {
            $brief->error_message === null => 'Generated from stored data',
            str_starts_with($brief->error_message, 'evaluation_') => 'Generated from stored data: the latest evaluation did not complete for every rule.',
            default => 'AI unavailable: generated from stored data',
        };
    }
}
