<?php

namespace App\Actions\Tasks;

use App\Enums\TaskDraftStatus;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskDraftApproved;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\Meetings\ExcerptVerifier;
use App\Services\Meetings\TextNormalizer;
use App\Services\Tasks\DraftEditor;
use App\Services\Tasks\IssueLifecycleBridge;
use App\Services\Tasks\TaskAssignmentNotifier;
use App\Services\Tasks\TaskStatusMachine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only code that turns a draft into a Task. One transaction: lock the draft row, re-read its status from the locked
 * row, re-check everything that may have changed (assignee eligibility, deadline, issue, evidence), create exactly one
 * task, save the approval metadata, write the assignment notification, then (after commit) apply the issue lifecycle.
 * Repeats, retries and concurrent requests return the existing task with no new writes, events or notifications; the
 * unique constraints on tasks.task_draft_id and task_drafts.task_id are the final safeguard.
 */
final class ApproveTaskDraft
{
    public function __construct(
        private readonly DraftEditor $editor,
        private readonly TaskAssignmentNotifier $notifier,
        private readonly IssueLifecycleBridge $issues,
        private readonly ExcerptVerifier $excerpts,
        private readonly TextNormalizer $text,
    ) {}

    /**
     * @param  array<string, mixed>  $edits  only keys: title, description, assignee_id, due_date, issue_id
     */
    public function handle(TaskDraft $routeDraft, User $actor, array $edits = []): ApprovalResult
    {
        try {
            $result = DB::transaction(function () use ($routeDraft, $actor, $edits): ApprovalResult {
                $draft = TaskDraft::query()->whereKey($routeDraft->getKey())->lockForUpdate()->firstOrFail();

                if ($draft->status === TaskDraftStatus::Approved) {
                    return new ApprovalResult(Task::query()->findOrFail($draft->task_id), false); // no write, no event
                }

                if ($draft->status !== TaskDraftStatus::Pending) {
                    throw ValidationException::withMessages(['status' => 'This draft can no longer be approved.']);
                }

                $meeting = Meeting::query()->with('department')->findOrFail($draft->meeting_id);

                // A draft whose supporting quote is no longer in the CURRENT transcript is stale and cannot be approved.
                if (! $this->supportedByTranscript($meeting, $draft)) {
                    throw ValidationException::withMessages(['draft' => 'The transcript changed and no longer supports this draft. Re-extract the tasks before approving.']);
                }

                $values = $this->editor->apply($draft, $meeting, $edits);
                $this->editor->assertApprovable($values, $meeting);

                $task = new Task([
                    'department_id' => $meeting->department_id,
                    'task_draft_id' => $draft->id,
                    'meeting_id' => $meeting->id,
                    'issue_id' => $values->issueId,
                    'title' => $values->title,
                    'description' => $values->description,
                    'assignee_id' => $values->assigneeId,
                    'assigned_by' => $actor->id,
                    'due_date' => $values->dueDate->format('Y-m-d'),
                ]);
                $task->forceFill(['status' => TaskStatusMachine::INITIAL])->save();

                $draft->forceFill($values->draftColumns() + [
                    'status' => TaskDraftStatus::Approved,
                    'task_id' => $task->id,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ])->save();

                $task->load(['assignee', 'department']);
                $this->notifier->send($task, $actor); // last step before commit: rolled back with the task
                TaskDraftApproved::dispatch($task, $actor->id, $draft->id, $task->assignee_id);
                TaskAssigned::dispatch($task, $actor->id, $task->assignee_id, null);

                return new ApprovalResult($task, true);
            });
        } catch (UniqueConstraintViolationException) {
            // Backstop: a competing approval won. The aborted transaction is gone; re-read outside it.
            return new ApprovalResult(Task::query()->where('task_draft_id', $routeDraft->getKey())->firstOrFail(), false);
        }

        if ($result->created && $result->task->issue_id !== null) {
            $this->issues->taskAdded($result->task->issue_id);
        }

        return $result;
    }

    private function supportedByTranscript(Meeting $meeting, TaskDraft $draft): bool
    {
        if ($meeting->transcript === null) {
            return false;
        }

        return $this->excerpts->verify($this->text->nfc($meeting->transcript), $draft->transcript_excerpt) !== null;
    }
}
