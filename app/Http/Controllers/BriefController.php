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
use App\Models\User;
use App\Services\AI\BriefFingerprint;
use App\Services\AI\BriefPresenter;
use App\Services\AI\OpenAiClient;
use App\Support\Flash;
use App\Support\TimeAgo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BriefController extends Controller
{
    /** An in-progress brief older than this no longer blocks a new request (the job's unique lock expires too). */
    private const STUCK_AFTER_MINUTES = 15;

    public function __construct(private readonly BriefPresenter $presenter) {}

    public function index(Request $request, TimeAgo $ago): Response
    {
        Gate::authorize('viewAny', Brief::class);
        $user = $request->user();
        $timezone = $user->company->timezone;

        // Optional date range on the brief's as-of date (the day its figures describe).
        $range = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);

        $briefs = Brief::visibleTo($user)
            ->when($range['from'] ?? null, fn ($query, $from) => $query->whereDate('as_of_date', '>=', $from))
            ->when($range['to'] ?? null, fn ($query, $to) => $query->whereDate('as_of_date', '<=', $to))
            ->latest('created_at')->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('briefs/Index', [
            'briefs' => $briefs->getCollection()->map(fn (Brief $brief) => [
                'id' => $brief->id,
                'status' => $brief->status->value,
                'source' => $brief->source?->value,
                'coverage_status' => $brief->coverage_status?->value,
                'headline' => $brief->headline,
                'as_of_date' => $brief->as_of_date?->format('Y-m-d'),
                'ago' => $ago->label($brief->generated_at ?? $brief->created_at),
                'created_at' => $brief->created_at->setTimezone($timezone)->toIso8601String(),
                'generated_at' => $brief->generated_at?->setTimezone($timezone)->toIso8601String(),
            ])->values(),
            'ai' => ['text' => OpenAiClient::textConfigured()],
            'filters' => ['from' => $range['from'] ?? null, 'to' => $range['to'] ?? null],
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

        // A "ready" brief whose file has disappeared is a visible failure, never a player that cannot play.
        if ($brief->audio_status === AudioStatus::Ready && ($brief->audio_path === null || ! Storage::disk('local')->exists($brief->audio_path))) {
            $brief->forceFill(['audio_status' => AudioStatus::Failed, 'audio_path' => null])->save();
        }

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
                'ago' => app(TimeAgo::class)->label($brief->generated_at ?? $brief->created_at),
                'notice' => $this->notice($brief),
                // Queued or writing for over 45 seconds: the background worker is probably not running.
                'stalled' => in_array($brief->status, [BriefStatus::Queued, BriefStatus::Generating], true) && $brief->updated_at->lt(now()->subSeconds(45)),
                'items' => array_map(fn (array $item) => $item + [
                    'issue_url' => in_array($item['issue_id'], $visibleIssueIds, true) ? route('issues.show', $item['issue_id']) : null,
                ], $blocks['items']),
            ],
            'ai' => ['text' => OpenAiClient::textConfigured()],
            'adjacent' => ['previous' => $this->neighbour($user, $brief, $timezone, earlier: true), 'next' => $this->neighbour($user, $brief, $timezone, earlier: false)],
            'audio' => [
                'status' => $brief->audio_status->value,
                'configured' => OpenAiClient::speechConfigured(),
                'url' => $brief->audio_status === AudioStatus::Ready && $brief->audio_path !== null ? route('briefs.audio.stream', $brief) : null,
                'can_request' => $brief->status === BriefStatus::Completed && in_array($brief->audio_status, [AudioStatus::None, AudioStatus::Failed], true),
            ],
        ]);
    }

    public function store(GenerateBriefRequest $request, BriefFingerprint $fingerprints): RedirectResponse
    {
        $user = $request->user();
        $fingerprint = $fingerprints->for($user);
        $scopeDepartment = $user->isManager() ? $user->department_id : null;

        $reused = null;

        $brief = DB::transaction(function () use ($user, $request, $fingerprint, $scopeDepartment, &$reused): ?Brief {
            // Serializes concurrent requests of one company so only one brief is created.
            Company::query()->whereKey($user->company_id)->lockForUpdate()->firstOrFail();

            // The same data and the same AI would write the same brief: open the existing one (unless a new one is asked for).
            if (! $request->boolean('force')) {
                $reused = Brief::query()->where('company_id', $user->company_id)->where('department_id', $scopeDepartment)
                    ->where('source_fingerprint', $fingerprint)
                    ->whereIn('status', [BriefStatus::Queued->value, BriefStatus::Generating->value, BriefStatus::Completed->value])
                    ->latest('id')->first();

                if ($reused !== null) {
                    return null;
                }
            }

            $inProgress = Brief::query()->where('company_id', $user->company_id)
                ->whereIn('status', [BriefStatus::Queued->value, BriefStatus::Generating->value])
                ->where('updated_at', '>', now()->subMinutes(self::STUCK_AFTER_MINUTES))
                ->exists();

            if ($inProgress) {
                throw ValidationException::withMessages(['brief' => 'A brief is already being generated. Please wait for it to finish.']);
            }

            return Brief::query()->create([
                'company_id' => $user->company_id,
                // A Manager's brief is scoped to the Manager's department; an Owner's is company-wide.
                'department_id' => $user->isManager() ? $user->department_id : null,
                'requested_by' => $user->id,
                'status' => BriefStatus::Queued,
                'source_fingerprint' => $fingerprint,
            ]);
        });

        if ($brief === null) {
            Flash::success('Your latest brief already matches the current data, so no new one was written. Use "Write a new brief" to write it again.');

            return to_route('briefs.show', $reused);
        }

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

    /**
     * The brief generated just before (earlier) or just after this one among the briefs the user may see, or null.
     *
     * @return array{id: int, url: string, label: string}|null
     */
    private function neighbour(User $user, Brief $brief, string $timezone, bool $earlier): ?array
    {
        $other = Brief::visibleTo($user)
            ->where(fn ($query) => $query->where('created_at', $earlier ? '<' : '>', $brief->created_at)
                ->orWhere(fn ($same) => $same->where('created_at', $brief->created_at)->where('id', $earlier ? '<' : '>', $brief->id)))
            ->orderBy('created_at', $earlier ? 'desc' : 'asc')->orderBy('id', $earlier ? 'desc' : 'asc')
            ->first();

        return $other === null ? null : [
            'id' => $other->id,
            'url' => route('briefs.show', $other),
            'label' => $other->created_at->setTimezone($timezone)->format('j M Y, H:i'),
        ];
    }
}
