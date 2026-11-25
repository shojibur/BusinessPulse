<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;

/**
 * Row-level validation. Money is parsed and held as decimal strings, never as floats. Zero is a valid
 * value and is not missing data.
 */
final class ImportRowValidator
{
    /**
     * @param  array<string, string>|null  $cells  null when the row has the wrong number of cells
     * @param  list<string>  $expectedBranches
     * @param  list<string>  $positionalCells  the raw cells, used to report a wrong column count
     */
    public function validate(int $rowNumber, ?array $cells, array $expectedBranches, CarbonImmutable $businessToday, array $positionalCells = []): RowValidationResult
    {
        if ($cells === null) {
            $raw = [];
            foreach ($positionalCells as $index => $cell) {
                $raw['column_'.($index + 1)] = $cell;
            }

            return new RowValidationResult($rowNumber, [
                new RowError($rowNumber, null, 'wrong_column_count', 'The row has a different number of cells than the header.', $raw),
            ], null);
        }

        $raw = $cells;
        $errors = [];
        $error = function (string $column, string $code, string $message) use (&$errors, $rowNumber, $raw): void {
            $errors[] = new RowError($rowNumber, $column, $code, $message, $raw);
        };

        $get = fn (string $column): string => trim($cells[$column] ?? '');

        // sales_date
        $date = null;
        $value = $get('sales_date');
        if ($value === '') {
            $error('sales_date', 'missing_value', 'The sales date is required.');
        } elseif (! $this->isCalendarDate($value)) {
            $error('sales_date', 'invalid_date', 'The sales date must be a real date in the format YYYY-MM-DD.');
        } else {
            $date = CarbonImmutable::parse($value, 'UTC');
            if ($date->format('Y-m-d') < config('imports.minimum_date')) {
                $error('sales_date', 'date_out_of_range', 'The sales date is before '.config('imports.minimum_date').'.');
                $date = null;
            } elseif ($date->format('Y-m-d') > $businessToday->format('Y-m-d')) {
                $error('sales_date', 'future_date', 'The sales date is in the future.');
                $date = null;
            }
        }

        // branch_code
        $branch = null;
        $value = $get('branch_code');
        if ($value === '') {
            $error('branch_code', 'missing_value', 'The branch code is required.');
        } elseif (preg_match(config('imports.columns.branch_code.regex'), $value) !== 1) {
            $error('branch_code', 'invalid_branch_format', 'The branch code must be 1 to 20 letters, digits, hyphens or underscores.');
        } else {
            $branch = strtoupper($value);
            if (! in_array($branch, $expectedBranches, true)) {
                $error('branch_code', 'unknown_branch', "Branch {$branch} is not an expected branch. Add it in Settings first.");
                $branch = null;
            }
        }

        $revenue = $this->money($get('revenue'), 'revenue', $error);
        $target = $this->money($get('target_revenue'), 'target_revenue', $error);

        // order_count
        $orders = null;
        $value = $get('order_count');
        if ($value !== '') {
            if (preg_match(config('imports.columns.order_count.regex'), $value) !== 1) {
                $error('order_count', 'invalid_integer', 'The order count must be a whole number of at most 9 digits, or empty.');
            } else {
                $orders = (int) $value;
            }
        }

        if ($errors !== [] || $date === null || $branch === null || $revenue === null || $target === null) {
            return new RowValidationResult($rowNumber, $errors, null);
        }

        return new RowValidationResult($rowNumber, [], new ValidRow($rowNumber, $date, $branch, $revenue, $target, $orders));
    }

    /**
     * Second pass: every row that shares a record identity with another valid row of the same file gets
     * a duplicate_in_file error naming the other row numbers.
     *
     * @param  array<int, ValidRow>  $validRowsByNumber
     * @param  array<int, array<string, string>>  $rawByNumber
     * @return array<int, list<RowError>> errors by row number
     */
    public function detectDuplicateIdentities(array $validRowsByNumber, array $rawByNumber): array
    {
        $byIdentity = [];
        foreach ($validRowsByNumber as $number => $row) {
            $byIdentity[$row->identity()][] = $number;
        }

        $errors = [];
        foreach ($byIdentity as $numbers) {
            if (count($numbers) < 2) {
                continue;
            }
            foreach ($numbers as $number) {
                $others = array_values(array_diff($numbers, [$number]));
                $errors[$number][] = new RowError(
                    $number,
                    'sales_date',
                    'duplicate_in_file',
                    'The same branch and date appear in row'.(count($others) > 1 ? 's ' : ' ').implode(', ', $others).'.',
                    $rawByNumber[$number] ?? [],
                );
            }
        }

        return $errors;
    }

    /**
     * Validate a money cell and return its 2-decimal string, or null after recording the error.
     *
     * @param  callable(string, string, string): void  $error
     */
    private function money(string $value, string $column, callable $error): ?string
    {
        $label = str_replace('_', ' ', $column);

        if ($value === '') {
            $error($column, 'missing_value', "The {$label} is required.");

            return null;
        }

        if (str_starts_with($value, '-')) {
            $error($column, 'negative_value', "The {$label} must not be negative.");

            return null;
        }

        if (preg_match(config("imports.columns.{$column}.regex"), $value) === 1) {
            return $this->twoDecimals($value);
        }

        if (preg_match('/^[0-9]+\.[0-9]{3,}$/', $value) === 1) {
            $error($column, 'too_many_decimals', "The {$label} must have at most 2 decimal places.");
        } elseif (preg_match('/^[0-9]{13,}(\.[0-9]{1,2})?$/', $value) === 1) {
            $error($column, 'value_out_of_range', "The {$label} is too large (at most 12 digits before the decimal point).");
        } else {
            $error($column, 'invalid_number', "The {$label} must be a plain decimal number such as 4520.50.");
        }

        return null;
    }

    private function twoDecimals(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    private function isCalendarDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
