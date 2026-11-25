<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;

/**
 * Valid rows of an unprocessed import, overlaid on Active_Records as if they were saved. Writes nothing.
 */
final readonly class PendingRows
{
    /**
     * @param  list<ValidRow>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @param  list<ClassifiedRow>  $classified
     */
    public static function fromClassified(array $classified): self
    {
        return new self(array_map(fn (ClassifiedRow $row) => $row->row, $classified));
    }

    /**
     * @return list<array{branch_code: string, sales_date: string}>
     */
    public function toRows(): array
    {
        return array_map(fn (ValidRow $row) => [
            'branch_code' => $row->branchCode,
            'sales_date' => $row->salesDate->format('Y-m-d'),
        ], $this->rows);
    }

    public function maxDate(): ?CarbonImmutable
    {
        $dates = array_map(fn (ValidRow $row) => $row->salesDate, $this->rows);

        return $dates === [] ? null : max($dates);
    }
}
