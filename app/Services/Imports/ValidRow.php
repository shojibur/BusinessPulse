<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;

/**
 * A fully validated row. Money is a 2-decimal string, never a float.
 */
final readonly class ValidRow
{
    public function __construct(
        public int $rowNumber,
        public CarbonImmutable $salesDate,
        public string $branchCode,
        public string $revenue,
        public string $targetRevenue,
        public ?int $orderCount,
    ) {}

    /** The record identity within a department. */
    public function identity(): string
    {
        return $this->branchCode.'|'.$this->salesDate->format('Y-m-d');
    }
}
