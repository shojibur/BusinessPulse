<?php

namespace App\Events\Tasks;

use App\Models\Task;

final class TaskDraftApproved extends TaskEvent
{
    public function __construct(Task $task, ?int $actorUserId, public readonly int $taskDraftId, public readonly int $assigneeId)
    {
        parent::__construct($task, $actorUserId);
    }
}
