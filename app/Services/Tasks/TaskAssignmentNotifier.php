<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;

/** Sends the in-app assignment notification once per (task, assignee); never to the person who assigned it. */
class TaskAssignmentNotifier
{
    public function send(Task $task, User $actor): void
    {
        $assignee = $task->assignee;

        if ($assignee->id === $actor->id) {
            return;
        }

        $key = "task_assigned:{$task->id}:{$assignee->id}";

        if ($assignee->notifications()->where('data->dedupe_key', $key)->exists()) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task, $actor->name, $task->department->name));
    }
}
