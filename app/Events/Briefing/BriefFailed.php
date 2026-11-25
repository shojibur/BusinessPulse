<?php

namespace App\Events\Briefing;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/** A brief could not be produced at all. Carries a sanitized code only. */
final class BriefFailed
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly int $briefId,
        public readonly int $companyId,
        public readonly string $reason,
        public readonly ?int $actorUserId = null,
    ) {
        $this->occurredAt = CarbonImmutable::now();
    }
}
