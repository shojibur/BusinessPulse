<?php

namespace App\Services\Tasks;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\IssueTransitionNotAllowedException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls the pure S04 IssueLifecycle transitions after a task change has committed. It never resolves an issue and
 * never writes outcomes: completing tasks can at most move an issue to `monitoring`; resolution belongs to S08 (or an
 * authorized manual resolution). A failure here never undoes a task.
 */
final class IssueLifecycleBridge
{
    public function __construct(private readonly IssueLifecycle $lifecycle, private readonly IssueTaskCompletion $completion) {}

    /** A linked task was approved or created. */
    public function taskAdded(int $issueId): void
    {
        $issue = Issue::query()->find($issueId);

        match ($issue?->status) {
            IssueStatus::Open, IssueStatus::Acknowledged => $this->safely($issueId, fn () => $this->lifecycle->startAction($issue, null)),
            IssueStatus::Monitoring => $this->safely($issueId, fn () => $this->lifecycle->returnToAction($issue, null)),
            default => null, // action_in_progress, resolved or missing
        };
    }

    /** A linked task became completed or cancelled. */
    public function tasksSettled(int $issueId): void
    {
        $issue = Issue::query()->find($issueId);

        if ($issue?->status === IssueStatus::ActionInProgress && $this->completion->isSatisfied($issueId)) {
            $this->safely($issueId, fn () => $this->lifecycle->startMonitoring($issue, null));
        }
    }

    /** A completed linked task was reopened. `resolved` is never touched. */
    public function taskReopened(int $issueId): void
    {
        $issue = Issue::query()->find($issueId);

        if ($issue?->status === IssueStatus::Monitoring) {
            $this->safely($issueId, fn () => $this->lifecycle->returnToAction($issue, null));
        }
    }

    private function safely(int $issueId, callable $call): void
    {
        try {
            $call();
        } catch (IssueTransitionNotAllowedException $e) {
            Log::info('task_issue_transition_skipped', ['issue_id' => $issueId, 'exception' => $e::class]);
        } catch (Throwable $e) {
            Log::error('task_issue_transition_failed', ['issue_id' => $issueId, 'exception' => $e::class]);
        }
    }
}
