<?php

namespace App\Events\Tasks;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base payload of every task event: ids only (no comment text, descriptions, reasons, transcript text or paths).
 * Dispatched after commit. S07 registers no listener and writes no activity log; S08 subscribes.
 */
abstract class TaskEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public readonly int $taskId;

    public readonly int $departmentId;

    public readonly int $companyId;

    public readonly ?int $issueId;

    public readonly ?int $meetingId;

    public function __construct(Task $task, public readonly ?int $actorUserId)
    {
        $this->taskId = $task->id;
        $this->departmentId = $task->department_id;
        $this->companyId = (int) $task->department()->value('company_id');
        $this->issueId = $task->issue_id;
        $this->meetingId = $task->meeting_id;
        $this->occurredAt = CarbonImmutable::now('UTC');
    }
}
