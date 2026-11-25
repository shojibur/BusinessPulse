<?php

namespace App\Events\Meetings;

use App\Models\Meeting;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base payload of every meeting event: ids only (no transcript, decision or draft text and no file paths). Dispatched
 * after commit. S06 registers no listener and writes no activity log; S08 subscribes.
 */
abstract class MeetingEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    /** @var list<int> */
    public readonly array $issueIds;

    public readonly int $meetingId;

    public readonly int $departmentId;

    public readonly int $companyId;

    public function __construct(Meeting $meeting, public readonly ?int $actorUserId = null)
    {
        $this->meetingId = $meeting->id;
        $this->departmentId = $meeting->department_id;
        $this->companyId = (int) $meeting->department()->value('company_id');
        $this->issueIds = $meeting->issues()->pluck('issues.id')->map(fn ($id) => (int) $id)->all();
        $this->occurredAt = app(BusinessClock::class)->nowUtc();
    }
}
