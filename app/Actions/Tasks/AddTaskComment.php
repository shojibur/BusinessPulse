<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

/** Adds a plain-text comment to a task the actor can view. Allowed in every status; it does not touch the task row. */
final class AddTaskComment
{
    public function handle(Task $task, User $actor, string $body): TaskComment
    {
        return TaskComment::query()->create(['task_id' => $task->id, 'user_id' => $actor->id, 'body' => trim($body)]);
    }
}
