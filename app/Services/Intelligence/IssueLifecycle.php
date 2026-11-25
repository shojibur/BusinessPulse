<?php

namespace App\Services\Intelligence;

use App\Enums\IssueStatus;
use App\Enums\ResolutionType;
use App\Events\Intelligence\IssueStatusChanged;
use App\Models\Issue;
use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The issue state machine (docs/stage-1-refinements.md R4). Pure transition methods: each runs in a transaction
 * with a row lock and a status check, appends an immutable status-history entry, uses the business clock, and
 * dispatches IssueStatusChanged after commit. A repeat of an already applied transition (double submit) is a
 * no-op. S04 never decides when linked tasks are complete: S07 calls startMonitoring / returnToAction, and S08
 * calls resolveVerified / reopen. Task completion alone never changes an issue.
 */
final class IssueLifecycle
{
    public const MAX_REASON = 2000;

    public function __construct(private readonly BusinessClock $clock) {}

    /** open -> acknowledged (Owner or Manager in scope; authorization is the caller's). */
    public function acknowledge(Issue $issue, User $actor): TransitionResult
    {
        return $this->transition($issue, IssueStatus::Acknowledged, $actor, null, 'manual');
    }

    /**
     * open | acknowledged -> action_in_progress, setting action_started_at once per action cycle.
     * Any other status is left unchanged without error.
     */
    public function startAction(Issue $issue, ?User $actor = null, string $trigger = 'task_approved'): TransitionResult
    {
        return $this->transition(
            $issue, IssueStatus::ActionInProgress, $actor, null, $trigger,
            apply: function (Issue $locked, $now): void {
                $locked->action_started_at ??= $now->utc();
            },
            onlyFrom: [IssueStatus::Open, IssueStatus::Acknowledged],
            ignoreOtherStatuses: true,
        );
    }

    /**
     * action_in_progress -> monitoring. With no actor (S07: all non-cancelled linked tasks are completed) no
     * reason is needed; with an actor (manual) a reason is required.
     */
    public function startMonitoring(Issue $issue, ?User $actor = null, ?string $reason = null): TransitionResult
    {
        if ($actor !== null) {
            $reason = $this->requiredReason($reason);
        }

        return $this->transition(
            $issue, IssueStatus::Monitoring, $actor, $reason, $actor === null ? 'tasks_complete' : 'manual',
            apply: function (Issue $locked, $now): void {
                $locked->monitoring_started_at = $now->utc();
            },
        );
    }

    /** monitoring -> action_in_progress; clears monitoring_started_at and keeps action_started_at. */
    public function returnToAction(Issue $issue, ?User $actor = null, ?string $reason = null): TransitionResult
    {
        return $this->transition(
            $issue, IssueStatus::ActionInProgress, $actor, $reason, 'task_reopened',
            apply: function (Issue $locked): void {
                $locked->monitoring_started_at = null;
            },
            onlyFrom: [IssueStatus::Monitoring],
        );
    }

    /** Any open status -> resolved by an Owner or Manager with a required reason. */
    public function resolveManual(Issue $issue, User $actor, ?string $reason): TransitionResult
    {
        $reason = $this->requiredReason($reason);

        return $this->transition(
            $issue, IssueStatus::Resolved, $actor, $reason, 'manual',
            apply: function (Issue $locked, $now) use ($actor, $reason): void {
                $locked->resolved_at = $now->utc();
                $locked->resolved_by = $actor->id;
                $locked->resolution_type = ResolutionType::Manual;
                $locked->resolution_note = $reason;
            },
        );
    }

    /** monitoring -> resolved by the system after a verified post-action outcome (S08). */
    public function resolveVerified(Issue $issue, ?string $outcomeReference = null): TransitionResult
    {
        return $this->transition(
            $issue, IssueStatus::Resolved, null, $outcomeReference, 'outcome_verified',
            apply: function (Issue $locked, $now): void {
                $locked->resolved_at = $now->utc();
                $locked->resolved_by = null;
                $locked->resolution_type = ResolutionType::SystemVerified;
                $locked->resolution_note = null;
            },
            onlyFrom: [IssueStatus::Monitoring],
        );
    }

    /** monitoring -> open (S08: a post-action outcome worsened). Keeps the baseline; starts a new action cycle. */
    public function reopen(Issue $issue, ?string $reason = null): TransitionResult
    {
        return $this->transition(
            $issue, IssueStatus::Open, null, $reason, 'outcome_worsened',
            apply: function (Issue $locked): void {
                $locked->action_started_at = null;
                $locked->monitoring_started_at = null;
            },
        );
    }

    /**
     * @param  callable(Issue, CarbonImmutable): void|null  $apply  extra column changes
     * @param  list<IssueStatus>|null  $onlyFrom  statuses this method may start from (others are not allowed)
     */
    private function transition(
        Issue $issue,
        IssueStatus $to,
        ?User $actor,
        ?string $reason,
        string $trigger,
        ?callable $apply = null,
        ?array $onlyFrom = null,
        bool $ignoreOtherStatuses = false,
    ): TransitionResult {
        $event = null;

        $result = DB::transaction(function () use ($issue, $to, $actor, $reason, $trigger, $apply, $onlyFrom, $ignoreOtherStatuses, &$event): TransitionResult {
            $locked = Issue::query()->whereKey($issue->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            // Double submit, or a start call on an issue that already moved on: nothing changes.
            if ($from === $to || ($ignoreOtherStatuses && ! in_array($from, $onlyFrom ?? [], true))) {
                return new TransitionResult($locked, false);
            }

            if (! $from->canTransitionTo($to) || ($onlyFrom !== null && ! in_array($from, $onlyFrom, true))) {
                throw new IssueTransitionNotAllowedException($from->value, $to->value);
            }

            $now = $this->clock->now();
            $apply?->__invoke($locked, $now);

            // Append only: earlier entries are never modified.
            $locked->status_history = [...$locked->status_history, [
                'from' => $from->value,
                'to' => $to->value,
                'at' => $now->toIso8601String(),
                'by_user_id' => $actor?->id,
                'reason' => $reason,
                'trigger' => $trigger,
            ]];
            $locked->status = $to;
            $locked->save();

            $event = new IssueStatusChanged($locked->department_id, $locked->id, $from->value, $to->value, $actor?->id, $reason);

            return new TransitionResult($locked, true);
        });

        if ($event !== null) {
            DB::afterCommit(fn () => event($event));
        }

        return $result;
    }

    /**
     * @throws ValidationException
     */
    private function requiredReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        if (mb_strlen($reason) > self::MAX_REASON) {
            throw ValidationException::withMessages(['reason' => 'The reason must be at most '.self::MAX_REASON.' characters.']);
        }

        return $reason;
    }
}
