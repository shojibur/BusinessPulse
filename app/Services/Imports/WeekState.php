<?php

namespace App\Services\Imports;

use App\Enums\WeekCompleteness;
use App\Enums\WeekStatus;
use Carbon\CarbonImmutable;

/**
 * Facts about one ISO reporting week. Rule decisions (insufficient_data, breaches) belong to S04.
 */
final readonly class WeekState
{
    /**
     * @param  list<CoverageGap>  $gaps  due branch-days without a record
     */
    public function __construct(
        public CarbonImmutable $weekStart,
        public CarbonImmutable $weekEnd,
        public WeekStatus $status,
        public ?WeekCompleteness $completeness,
        public int $expectedBranchDays,
        public int $presentBranchDays,
        public int $dueBranchDays,
        public ?string $coveragePct,
        public array $gaps,
    ) {}
}
