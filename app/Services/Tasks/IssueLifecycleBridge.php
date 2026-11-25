<?php

namespace App\Services\Tasks;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\IssueTransitionNotAllowedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the S04 IssueLifecycle transitions that follow task changes. Every method takes the ISSUE row lock first, then
 * decides from the CURRENT task counts inside that lock, so concurrent approvals, completions, cancellations and reopens are
 * serialized per issue: an issue can never be left in `monitoring` while a linked task is open, and never moves to
 * `monitoring` on a stale reading. It never resolves an issue and never writes outcomes. `reconcile()` is also the
 * recovery path: any later task change, and every outcome verification run, re-applies it, so a failed lifecycle call is
 * corrected the next time the issue is touched.
 */
class IssueLifecycleBridge
{
    public function __construct(private readonly IssueLifecycle $lifecycle, private readonly IssueTaskCompletion $completion) {}

    /** A linked task was approved or created. */
    public function taskAdded(int $issueId): void
    {
        $this->reconcile($issueId, startAction: true);
    }

    /** A linked task became completed or cancelled. */
    public function tasksSettled(int $issueId): void
    {
        $this->reconcile($issueId);
    }

    /** A completed linked task was reopened. `resolved` is never touched. */
    public function taskReopened(int $issueId): void
    {
        $this->reconcile($issueId);
    }

    /**
     * Brings the issue status in line with its tasks, under the issue row lock:
     *  - open or acknowledged and a task was just added: start the action;
     *  - monitoring while any linked task is open: back to action in progress;
     *  - action in progress and the completion condition holds: monitoring.
     * Returns whether a transition was applied.
     */
    public function reconcile(int $issueId, bool $startAction = false): bool
    {
        try {
            return DB::transaction(function () use ($issueId, $startAction): bool {
                $issue = Issue::query()->whereKey($issueId)->lockForUpdate()->first();

                if ($issue === null) {
                    return false;
                }

                $applied = false;

                try {
                    if ($startAction && in_array($issue->status, [IssueStatus::Open, IssueStatus::Acknowledged], true)) {
                        $this->lifecycle->startAction($issue, null);
                        $issue->refresh();
                        $applied = true;
                    }

                    if ($issue->status === IssueStatus::Monitoring && $this->completion->hasOpenTask($issueId)) {
                        $this->lifecycle->returnToAction($issue, null);
                        $issue->refresh();
                        $applied = true;
                    }

                    if ($issue->status === IssueStatus::ActionInProgress && $this->completion->isSatisfied($issueId)) {
                        $this->lifecycle->startMonitoring($issue, null);
                        $applied = true;
                    }
                } catch (IssueTransitionNotAllowedException $e) {
                    Log::info('task_issue_transition_skipped', ['issue_id' => $issueId, 'exception' => $e::class]);
                }

                return $applied;
            });
        } catch (Throwable $e) {
            // The task change stays committed; the next task change or outcome verification run reconciles again.
            Log::error('task_issue_transition_failed', ['issue_id' => $issueId, 'exception' => $e::class]);

            return false;
        }
    }
}
