<?php

namespace App\Events\Outcomes;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A useful / not useful vote was stored. No note text. */
final class FeedbackSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ?int $outcomeId,
        public readonly int $issueId,
        public readonly int $departmentId,
        public readonly int $companyId,
        public readonly ?int $actorUserId,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $targetType,
        public readonly bool $useful,
        public readonly bool $hasNote,
        public readonly bool $isUpdate,
    ) {}
}
