<?php

namespace App\Services\Dashboard;

use App\Models\Company;
use App\Models\Department;
use App\Services\Imports\ReportingCoverage;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sales of one day by branch, for the dashboard's "Daily sales" card. Exact decimal sums over the active records of
 * that day; a missing branch is shown as missing, never as zero. Informational only: rules and briefs stay weekly.
 */
final class DailySalesView
{
    public function __construct(
        private readonly MetricCalculator $metrics,
        private readonly ReportingCoverage $coverage,
        private readonly BusinessClock $clock,
    ) {}

    /**
     * @return array<string, mixed>|null null when the department has no sales data at all
     */
    public function for(Company $company, Department $department, ?CarbonImmutable $requested, ?CarbonImmutable $latestSalesDate): ?array
    {
        $range = DB::selectOne('select min(sales_date)::text as first, max(sales_date)::text as last from sales_records where department_id = ? and is_active', [$department->id]);

        if ($range === null || $range->first === null) {
            return null;
        }

        $first = CarbonImmutable::parse($range->first, 'UTC');
        $default = $latestSalesDate ?? CarbonImmutable::parse($range->last, 'UTC');
        // The company's calendar date today (not converted to UTC, which would shift the date).
        $today = CarbonImmutable::parse($this->clock->today($company->timezone)->format('Y-m-d'), 'UTC');
        $max = $today->greaterThan($default) ? $today : $default;

        // Only days from the first sales date up to today can be chosen; anything else shows the latest sales day.
        $day = $requested !== null && ! $requested->lessThan($first) && ! $requested->greaterThan($max) ? $requested : $default;

        $expected = $this->coverage->expectedBranches($department);
        $rows = collect(DB::select(
            'select branch_code, sum(revenue)::text as revenue, sum(target_revenue)::text as target from sales_records
              where department_id = ? and is_active and sales_date = ? group by branch_code order by branch_code',
            [$department->id, $day->format('Y-m-d')],
        ))->keyBy('branch_code');

        $codes = collect($expected)->merge($rows->keys())->unique()->sort()->values();
        $branches = $codes->map(function (string $code) use ($rows) {
            $row = $rows->get($code);

            return $row === null
                ? ['code' => $code, 'reported' => false, 'revenue' => null, 'target' => null, 'achievement' => null]
                : $this->line($code, $row->revenue, $row->target);
        })->all();

        $reported = collect($branches)->where('reported', true);
        $totals = DB::selectOne('select coalesce(sum(revenue), 0)::text as revenue, coalesce(sum(target_revenue), 0)::text as target from sales_records where department_id = ? and is_active and sales_date = ?', [$department->id, $day->format('Y-m-d')]);
        $missing = collect($expected)->reject(fn (string $code) => $rows->has($code))->values()->all();
        $trading = in_array($day->dayOfWeekIso, $company->trading_days, true);
        $due = $this->coverage->isDue($department, $day);

        $status = match (true) {
            $rows->isNotEmpty() && $missing === [] => 'complete',
            $rows->isNotEmpty() => 'partial',
            ! $trading => 'non_trading',
            ! $due => 'not_due',
            default => 'none',
        };

        return [
            'date' => $day->format('Y-m-d'),
            'default_date' => $default->format('Y-m-d'),
            'min_date' => $first->format('Y-m-d'),
            'max_date' => $max->format('Y-m-d'),
            'status' => $status,
            'message' => match ($status) {
                'partial' => count($missing).' of '.count($expected).' expected branches have not reported for this day: '.implode(', ', $missing).'.',
                'non_trading' => 'This is not a trading day for the company.',
                'not_due' => 'Sales for this day are not due yet.',
                'none' => 'No sales were recorded for this day.',
                default => null,
            },
            'total' => $rows->isEmpty() ? null : $this->line('Total', $totals->revenue, $totals->target) + ['reported_count' => $reported->count(), 'expected_count' => count($expected)],
            'branches' => $branches,
            'missing' => $missing,
        ];
    }

    /**
     * @return array{code: string, reported: bool, revenue: string, target: string, achievement: string|null}
     */
    private function line(string $code, string $revenue, string $target): array
    {
        $pct = $this->metrics->targetAchievementPct($revenue, $target);

        return [
            'code' => $code,
            'reported' => true,
            'revenue' => $this->metrics->formatMoney($revenue),
            'target' => $this->metrics->formatMoney($target),
            'achievement' => $pct === Indeterminate::ZeroDenominator ? null : $this->metrics->display2($pct).'%',
        ];
    }
}
