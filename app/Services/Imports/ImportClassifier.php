<?php

namespace App\Services\Imports;

use App\Enums\ImportRowResult;
use App\Models\SalesRecord;

/**
 * Labels valid rows new, corrected or unchanged against the department's Active_Records.
 * Money is compared as 2-decimal strings (bcmath), never as floats.
 */
final class ImportClassifier
{
    /**
     * @param  list<ValidRow>  $validRows
     * @return list<ClassifiedRow>
     */
    public function classify(int $departmentId, array $validRows): array
    {
        if ($validRows === []) {
            return [];
        }

        $dates = array_map(fn (ValidRow $row) => $row->salesDate->format('Y-m-d'), $validRows);

        // One indexed lookup for the touched date range and branches (no per-row queries).
        $active = SalesRecord::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->whereBetween('sales_date', [min($dates), max($dates)])
            ->whereIn('branch_code', array_values(array_unique(array_map(fn (ValidRow $row) => $row->branchCode, $validRows))))
            ->get()
            ->keyBy(fn (SalesRecord $record) => $record->branch_code.'|'.$record->sales_date->format('Y-m-d'));

        return array_map(function (ValidRow $row) use ($active): ClassifiedRow {
            /** @var SalesRecord|null $existing */
            $existing = $active->get($row->identity());

            if ($existing === null) {
                return new ClassifiedRow($row, ImportRowResult::New, null);
            }

            return new ClassifiedRow($row, $this->sameValues($row, $existing) ? ImportRowResult::Unchanged : ImportRowResult::Corrected, $existing);
        }, $validRows);
    }

    private function sameValues(ValidRow $row, SalesRecord $record): bool
    {
        return bcadd($row->revenue, '0', 2) === bcadd((string) $record->revenue, '0', 2)
            && bcadd($row->targetRevenue, '0', 2) === bcadd((string) $record->target_revenue, '0', 2)
            // A null order count equals only a null order count.
            && $row->orderCount === $record->order_count;
    }
}
