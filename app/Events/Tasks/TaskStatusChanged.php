<?php

namespace App\Events\Tasks;

use App\Models\Task;

final class TaskStatusChanged extends TaskEvent
{
    public function __construct(Task $task, ?int $actorUserId, public readonly string $from, public readonly string $to)
    {
        parent::__construct($task, $actorUserId);
    }
}
