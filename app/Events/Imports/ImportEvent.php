<?php

namespace App\Events\Imports;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base payload of every import event: importId, departmentId, actorUserId (null for system events) and
 * occurredAt. Dispatched after the transaction that changed the data has committed.
 */
abstract class ImportEvent
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly int $importId,
        public readonly int $departmentId,
        public readonly ?int $actorUserId = null,
    ) {
        $this->occurredAt = app(BusinessClock::class)->nowUtc();
    }
}
