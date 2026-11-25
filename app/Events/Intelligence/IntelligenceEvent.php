<?php

namespace App\Events\Intelligence;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base payload of every intelligence event: departmentId, actorUserId (null for the system) and occurredAt.
 * Events carry ids only: no sales rows, file paths or personal data. They are dispatched after commit.
 */
abstract class IntelligenceEvent
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly int $departmentId,
        public readonly ?int $actorUserId = null,
    ) {
        $this->occurredAt = app(BusinessClock::class)->nowUtc();
    }
}
