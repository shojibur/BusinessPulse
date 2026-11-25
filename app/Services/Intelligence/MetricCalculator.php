<?php

namespace App\Services\Intelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic metrics with decimal-string (bcmath) arithmetic. No floats and no PHP rounding or formatting helpers.
 * Money is 2 decimals; percentages are 4 decimals internally and 2 for display. Double rounding (12, 4, 2
 * places) is intentional and deterministic: 124000 / 156000 -> 79.487179... -> 79.4872 -> 79.49.
 *
 * The calculator never decides whether a period is comparable (the evaluators do) and never substitutes zero
 * for a missing branch-day.
 */
final class MetricCalculator
{
    public const CALC_SCALE = 12;

    /** Round half up to the given number of places, never returning "-0.0000". */
    public function roundHalfUp(string $value, int $places): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        $half = '0.'.str_repeat('0', $places).'5';

        // bcadd truncates beyond `places`, so adding half of the last place rounds half up.
        $rounded = bcadd($absolute, $half, $places);

        if (bccomp($rounded, '0', $places) === 0) {
            return $rounded;
        }

        return $negative ? '-'.$rounded : $rounded;
    }

    /** numerator / denominator * 100, 4 decimals. */
    public function pct(string $numerator, string $denominator): string|Indeterminate
    {
        if (bccomp($denominator, '0', 2) === 0) {
            return Indeterminate::ZeroDenominator;
        }

        $quotient = bcdiv(bcmul($numerator, '100', self::CALC_SCALE), $denominator, self::CALC_SCALE);

        return $this->roundHalfUp($quotient, 4);
    }

    public function targetAchievementPct(string $revenue, string $target): string|Indeterminate
    {
        return $this->pct($revenue, $target);
    }

    public function weeklyChangePct(string $current, string $comparison): string|Indeterminate
    {
        return $this->pct(bcsub($current, $comparison, 2), $comparison);
    }

    public function coveragePct(int $present, int $expected): string|Indeterminate
    {
        return $this->pct((string) $present, (string) $expected);
    }

    /** A 4-decimal value rounded half up to 2 decimals for display and storage. */
    public function display2(string $value4): string
    {
        return $this->roundHalfUp($value4, 2);
    }

    /** "124000.00" -> "RM 124,000.00" (string work only, no float formatting). */
    public function formatMoney(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim(bcadd($amount, '0', 2), '-'), 2), 2, '00');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return ($negative ? '-' : '').'RM '.$grouped.'.'.$fraction;
    }

    /**
     * Revenue and target of one ISO week over Active_Records, summed exactly in SQL (numeric), plus the ids
     * of the contributing records and imports. One query.
     */
    public function weekTotals(int $departmentId, CarbonImmutable $weekStart, ?array $tradingDays = null, ?array $branches = null): WeekTotals
    {
        return $this->totalsBetween($departmentId, $weekStart, $weekStart->addDays(6), $tradingDays, $branches);
    }

    /**
     * `$tradingDays` (ISO weekdays) and `$branches` (expected branch codes) restrict the sums to what the reporting
     * calendar expects (R2): records on non-trading days or for non-expected branches never count towards a
     * figure, exactly as they never count towards coverage. Null means unrestricted (used by unit tests only).
     *
     * @param  list<int>|null  $tradingDays
     * @param  list<string>|null  $branches
     */
    public function totalsBetween(int $departmentId, CarbonImmutable $from, CarbonImmutable $to, ?array $tradingDays = null, ?array $branches = null): WeekTotals
    {
        $bindings = [$departmentId, $from->format('Y-m-d'), $to->format('Y-m-d')];
        $filter = '';

        if ($tradingDays !== null) {
            $filter .= ' and extract(isodow from sales_date)::int in ('.implode(',', array_fill(0, max(count($tradingDays), 1), '?')).')';
            array_push($bindings, ...($tradingDays === [] ? [0] : array_map('intval', $tradingDays)));
        }

        if ($branches !== null) {
            $filter .= ' and branch_code in ('.implode(',', array_fill(0, max(count($branches), 1), '?')).')';
            array_push($bindings, ...($branches === [] ? [''] : array_values($branches)));
        }

        $row = DB::selectOne(
            "select coalesce(sum(revenue), 0)::text as revenue,
                    coalesce(sum(target_revenue), 0)::text as target,
                    coalesce(array_to_json(array_agg(id order by id)), '[]'::json)::text as record_ids,
                    coalesce(array_to_json(array_agg(distinct import_id)), '[]'::json)::text as import_ids
               from sales_records
              where department_id = ? and is_active and sales_date between ? and ?{$filter}",
            $bindings,
        );

        $imports = json_decode($row->import_ids, true);
        sort($imports);

        return new WeekTotals(
            bcadd($row->revenue, '0', 2),
            bcadd($row->target, '0', 2),
            array_map('intval', json_decode($row->record_ids, true)),
            array_map('intval', $imports),
        );
    }
}
