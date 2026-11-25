<?php

namespace App\Events\Briefing;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/** A brief finished (AI or deterministic fallback). Ids and counts only; no figures, evidence or text. */
final class BriefGenerated
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(
        public readonly int $briefId,
        public readonly int $companyId,
        public readonly string $source,
        public readonly int $itemCount = 0,
        public readonly ?int $actorUserId = null,
    ) {
        $this->occurredAt = app(BusinessClock::class)->nowUtc();
    }
}
