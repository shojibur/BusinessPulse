<?php

namespace App\Services\Imports;

/**
 * Everything the preview page shows, as plain arrays safe to send to the frontend.
 */
final readonly class ImportPreviewData
{
    /**
     * @param  array<string, int>  $counts  total, valid, invalid, new, corrected, unchanged
     * @param  list<string>  $branches
     * @param  list<array<string, mixed>>  $weeks
     * @param  list<array{id: int, original_filename: string}>  $alreadyImported
     * @param  array<string, mixed>  $uncovered
     */
    public function __construct(
        public array $counts,
        public ?string $minDate,
        public ?string $maxDate,
        public array $branches,
        public int $nonTradingDayRows,
        public array $weeks,
        public array $alreadyImported,
        public bool $refreshed,
        public array $uncovered,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'min_date' => $this->minDate,
            'max_date' => $this->maxDate,
            'branches' => $this->branches,
            'non_trading_day_rows' => $this->nonTradingDayRows,
            'weeks' => $this->weeks,
            'already_imported' => $this->alreadyImported,
            'refreshed' => $this->refreshed,
            'uncovered' => $this->uncovered,
        ];
    }
}
