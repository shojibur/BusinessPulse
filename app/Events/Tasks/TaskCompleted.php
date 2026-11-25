<?php

namespace App\Events\Tasks;

use App\Models\Task;

final class TaskCompleted extends TaskEvent
{
    public function __construct(Task $task, ?int $actorUserId, public readonly int $assigneeId, public readonly string $completedAt)
    {
        parent::__construct($task, $actorUserId);
    }
}
