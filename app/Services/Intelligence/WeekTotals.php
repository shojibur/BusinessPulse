<?php

namespace App\Services\Intelligence;

/**
 * Exact weekly sums over Active_Records, with the ids that contributed (for evidence and the run key).
 */
final readonly class WeekTotals
{
    /**
     * @param  list<int>  $recordIds
     * @param  list<int>  $importIds
     */
    public function __construct(
        public string $revenue,
        public string $target,
        public array $recordIds,
        public array $importIds,
    ) {}
}
