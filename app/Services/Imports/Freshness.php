<?php

namespace App\Services\Imports;

use App\Enums\FreshnessState;
use Carbon\CarbonImmutable;

final readonly class Freshness
{
    public function __construct(
        public ?CarbonImmutable $latestSalesDate,
        public CarbonImmutable $lastDueDate,
        public FreshnessState $state,
        /** Null when no Active_Record exists. */
        public ?int $overdueBranchDays,
        public ?CarbonImmutable $overdueFrom,
        public ?CarbonImmutable $overdueTo,
    ) {}
}
