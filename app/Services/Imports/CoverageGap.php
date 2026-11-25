<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;

final readonly class CoverageGap
{
    public const INSIDE_PERIOD = 'inside_period';

    public const AFTER_LATEST = 'after_latest';

    public function __construct(
        public string $branchCode,
        public CarbonImmutable $date,
        /** inside_period: not later than the latest sales date. after_latest: later than it. */
        public string $kind,
    ) {}
}
