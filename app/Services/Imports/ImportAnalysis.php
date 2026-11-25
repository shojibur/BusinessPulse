<?php

namespace App\Services\Imports;

use App\Enums\ImportRowResult;
use Carbon\CarbonImmutable;

/**
 * The result of validating and classifying one file. The same object feeds the stored counters, the
 * preview and the processing job, so the three can never disagree about a file.
 */
final readonly class ImportAnalysis
{
    /**
     * @param  list<ClassifiedRow>  $classified  valid rows in row order
     * @param  list<RowError>  $errors  every row problem in row then column order
     */
    public function __construct(
        public int $totalRows,
        public array $classified,
        public array $errors,
        public int $invalidRows,
    ) {}

    public function validRows(): int
    {
        return count($this->classified);
    }

    public function count(ImportRowResult $result): int
    {
        return count(array_filter($this->classified, fn (ClassifiedRow $row) => $row->result === $result));
    }

    public function minDate(): ?CarbonImmutable
    {
        $dates = array_map(fn (ClassifiedRow $row) => $row->row->salesDate, $this->classified);

        return $dates === [] ? null : min($dates);
    }

    public function maxDate(): ?CarbonImmutable
    {
        $dates = array_map(fn (ClassifiedRow $row) => $row->row->salesDate, $this->classified);

        return $dates === [] ? null : max($dates);
    }

    /**
     * @return list<string> distinct branch codes of the valid rows, sorted
     */
    public function branches(): array
    {
        $branches = array_values(array_unique(array_map(fn (ClassifiedRow $row) => $row->row->branchCode, $this->classified)));
        sort($branches);

        return $branches;
    }
}
