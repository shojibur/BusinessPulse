<?php

namespace App\Events\Tasks;

use App\Models\Task;

final class TaskAssigned extends TaskEvent
{
    public function __construct(Task $task, ?int $actorUserId, public readonly int $assigneeId, public readonly ?int $previousAssigneeId = null)
    {
        parent::__construct($task, $actorUserId);
    }
}
