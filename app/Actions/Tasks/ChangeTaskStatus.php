<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\IssueLifecycleBridge;
use App\Services\Tasks\TaskStatusMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Changes a task's status through the machine. Same status = no-op. A transition the table does not allow is 422 for
 * everybody; an allowed transition the actor may not perform is 403 (Staff cannot cancel or reopen).
 */
final class ChangeTaskStatus
{
    public function __construct(private readonly TaskStatusMachine $machine, private readonly IssueLifecycleBridge $issues) {}

    public function handle(Task $task, TaskStatus $to, User $actor): Task
    {
        $current = $task->status;

        if ($to === $current) {
            return $task;
        }

        if (! TaskStatusMachine::allows($current, $to)) {
            throw ValidationException::withMessages(['status' => "A task that is {$current->label()} cannot become {$to->label()}."]);
        }

        Gate::forUser($actor)->authorize('changeStatus', [$task, $to]);

        $moved = DB::transaction(function () use ($task, $current, $to, $actor): bool {
            if (! $this->machine->apply($task, $current, $to)) {
                $fresh = Task::query()->whereKey($task->id)->value('status');

                if ($fresh === $to->value) {
                    return false; // a duplicate request already made this change
                }

                throw ValidationException::withMessages(['status' => 'This task was just changed (it is now '.TaskStatus::from($fresh)->label().'). Reload and try again.']);
            }

            $fresh = Task::query()->findOrFail($task->id);
            TaskStatusChanged::dispatch($fresh, $actor->id, $current->value, $to->value);

            if ($to === TaskStatus::Completed) {
                TaskCompleted::dispatch($fresh, $actor->id, $fresh->assignee_id, $fresh->completed_at->toIso8601String());
            }

            return true;
        });

        $task->refresh();

        if ($moved && $task->issue_id !== null) {
            if ($to === TaskStatus::Completed || $to === TaskStatus::Cancelled) {
                $this->issues->tasksSettled($task->issue_id);
            } elseif ($current === TaskStatus::Completed && $to === TaskStatus::InProgress) {
                $this->issues->taskReopened($task->issue_id);
            }
        }

        return $task;
    }
}
