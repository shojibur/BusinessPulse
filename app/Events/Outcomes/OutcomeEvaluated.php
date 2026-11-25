<?php

namespace App\Events\Outcomes;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A NEW outcome row was inserted (informational and not-evaluable rows included). Never on a conflict. No figures or text. */
final class OutcomeEvaluated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $outcomeId,
        public readonly int $issueId,
        public readonly int $departmentId,
        public readonly int $companyId,
        public readonly ?int $actorUserId,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $result,
        public readonly string $phase,
        public readonly bool $comparable,
        public readonly bool $resolutionConditionMet,
        public readonly string $lifecycleAction,
    ) {}
}
