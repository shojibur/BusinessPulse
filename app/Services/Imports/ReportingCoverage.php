<?php

namespace App\Services\Imports;

use App\Enums\FreshnessState;
use App\Enums\WeekCompleteness;
use App\Enums\WeekStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\SalesRecord;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;

/**
 * Calendar and coverage FACTS for a department: due days, closed weeks, completeness, missing due
 * branch-days and freshness. It makes no rule decision (breach, insufficient_data, issues belong to S04),
 * holds no state, writes nothing, and never turns a gap into zero.
 *
 * Every method that depends on time accepts an optional `$now`; it defaults to BusinessClock::now().
 * `$pending` overlays the valid rows of an unprocessed import as if they were Active_Records.
 */
final class ReportingCoverage
{
    public function __construct(
        private readonly BusinessClock $clock,
        private readonly ExpectedBranches $expectedBranches,
    ) {}

    public function calendar(Company $company, ?CarbonImmutable $now = null): ReportingCalendar
    {
        return ReportingCalendar::forCompany($company, $now ?? $this->clock->now($company->timezone));
    }

    /** @return list<string> */
    public function expectedBranches(Department $department): array
    {
        return $this->expectedBranches->get($department);
    }

    public function isDue(Department $department, CarbonImmutable $date, ?CarbonImmutable $now = null): bool
    {
        return $this->calendarFor($department, $now)->isDue($date);
    }

    public function lastDueDate(Department $department, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return $this->calendarFor($department, $now)->lastDueDate();
    }

    /** @return list<CarbonImmutable> */
    public function tradingDates(Department $department, CarbonImmutable $weekStart): array
    {
        return $this->calendarFor($department)->tradingDates($weekStart);
    }

    public function weekState(Department $department, CarbonImmutable $weekStart, ?PendingRows $pending = null, ?CarbonImmutable $now = null): WeekState
    {
        $monday = ReportingCalendar::weekStart($weekStart);

        return $this->weekStates($department, $monday, $monday, $pending, $now)[0];
    }

    /**
     * One range query for every week between the two Mondays (inclusive).
     *
     * @return list<WeekState>
     */
    public function weekStates(Department $department, CarbonImmutable $fromWeekStart, CarbonImmutable $toWeekStart, ?PendingRows $pending = null, ?CarbonImmutable $now = null): array
    {
        $calendar = $this->calendarFor($department, $now);
        $branches = $this->expectedBranches($department);
        $from = ReportingCalendar::weekStart($fromWeekStart);
        $to = ReportingCalendar::weekStart($toWeekStart);

        $rows = $this->activeRows($department, $from, $to->addDays(6), $pending);

        $states = [];
        for ($week = $from; $week <= $to; $week = $week->addDays(7)) {
            $states[] = $this->weekStateForRows($calendar, $branches, $week, $rows);
        }

        return $states;
    }

    /**
     * Database-free calculation, shared with weekState(). Used by demo:check-fixtures and tests.
     *
     * @param  list<string>  $expectedBranches
     * @param  iterable<array{branch_code: string, sales_date: string}>  $rows
     */
    public function weekStateForRows(ReportingCalendar $calendar, array $expectedBranches, CarbonImmutable $weekStart, iterable $rows): WeekState
    {
        $monday = ReportingCalendar::weekStart($weekStart);
        $tradingDates = $calendar->tradingDates($monday);
        $dateKeys = array_map(fn (CarbonImmutable $date) => $date->format('Y-m-d'), $tradingDates);
        $branchCount = count($expectedBranches);

        // Present means an active record for an expected branch on a trading day of the week. Records on
        // non-trading days and for other branches are ignored; a zero revenue is still present.
        $present = [];
        foreach ($rows as $row) {
            if (in_array($row['sales_date'], $dateKeys, true) && in_array($row['branch_code'], $expectedBranches, true)) {
                $present[$row['branch_code'].'|'.$row['sales_date']] = true;
            }
        }

        $due = array_values(array_filter($tradingDates, fn (CarbonImmutable $date) => $calendar->isDue($date)));
        $closed = count($due) === count($tradingDates);

        $gaps = [];
        foreach ($due as $date) {
            foreach ($expectedBranches as $branch) {
                if (! isset($present[$branch.'|'.$date->format('Y-m-d')])) {
                    $gaps[] = new CoverageGap($branch, $date, CoverageGap::INSIDE_PERIOD);
                }
            }
        }

        $expectedDays = $branchCount * count($tradingDates);
        $presentDays = count($present);

        $completeness = null;
        if ($closed) {
            $completeness = match (true) {
                $presentDays === 0 || $branchCount === 0 => WeekCompleteness::Absent,
                $presentDays < $expectedDays => WeekCompleteness::Incomplete,
                default => WeekCompleteness::Complete,
            };
        }

        return new WeekState(
            weekStart: $monday,
            weekEnd: $monday->addDays(6),
            status: $closed ? WeekStatus::Closed : WeekStatus::Open,
            completeness: $completeness,
            expectedBranchDays: $expectedDays,
            presentBranchDays: $presentDays,
            dueBranchDays: $branchCount * count($due),
            coveragePct: $this->percent($presentDays, $expectedDays),
            gaps: $gaps,
        );
    }

    /**
     * The most recent closed week, whatever its completeness. S04 decides what that means for a rule.
     */
    public function latestClosedWeek(Department $department, ?CarbonImmutable $now = null): WeekState
    {
        $calendar = $this->calendarFor($department, $now);
        $week = ReportingCalendar::weekStart($calendar->lastDueDate());

        if (! $calendar->isWeekClosed($week)) {
            $week = $week->subDays(7);
        }

        return $this->weekState($department, $week, null, $now);
    }

    /**
     * Expected branch x due trading day combinations with no record, within the window and never later
     * than the last due date.
     *
     * @return list<CoverageGap>
     */
    public function missingDueBranchDays(Department $department, CarbonImmutable $windowStart, CarbonImmutable $windowEnd, ?PendingRows $pending = null, ?CarbonImmutable $now = null): array
    {
        $calendar = $this->calendarFor($department, $now);
        $branches = $this->expectedBranches($department);
        $end = min($windowEnd, $calendar->lastDueDate());
        $start = CarbonImmutable::parse($windowStart->format('Y-m-d'), 'UTC');

        if ($start > $end) {
            return [];
        }

        $present = [];
        foreach ($this->activeRows($department, $start, $end, $pending) as $row) {
            $present[$row['branch_code'].'|'.$row['sales_date']] = true;
        }

        $latest = $this->latestSalesDate($department, $pending);
        $gaps = [];

        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            if (! $calendar->isDue($date)) {
                continue;
            }
            foreach ($branches as $branch) {
                if (! isset($present[$branch.'|'.$date->format('Y-m-d')])) {
                    $gaps[] = new CoverageGap(
                        $branch,
                        $date,
                        $latest !== null && $date <= $latest ? CoverageGap::INSIDE_PERIOD : CoverageGap::AFTER_LATEST,
                    );
                }
            }
        }

        return $gaps;
    }

    public function latestSalesDate(Department $department, ?PendingRows $pending = null): ?CarbonImmutable
    {
        $stored = SalesRecord::query()->where('department_id', $department->id)->where('is_active', true)->max('sales_date');
        $dates = array_filter([
            $stored === null ? null : CarbonImmutable::parse($stored, 'UTC'),
            $pending?->maxDate(),
        ]);

        return $dates === [] ? null : max($dates);
    }

    public function freshness(Department $department, ?PendingRows $pending = null, ?CarbonImmutable $now = null): Freshness
    {
        $calendar = $this->calendarFor($department, $now);
        $lastDue = $calendar->lastDueDate();
        $latest = $this->latestSalesDate($department, $pending);
        $current = $latest !== null && $latest >= $lastDue;

        if ($latest === null) {
            return new Freshness(null, $lastDue, FreshnessState::Behind, null, null, null);
        }

        // Due trading days after the latest sales date and up to the last due date, for every expected branch.
        $overdueDates = [];
        for ($date = $latest->addDay(); $date <= $lastDue; $date = $date->addDay()) {
            if ($calendar->isDue($date)) {
                $overdueDates[] = $date;
            }
        }

        return new Freshness(
            latestSalesDate: $latest,
            lastDueDate: $lastDue,
            state: $current ? FreshnessState::Current : FreshnessState::Behind,
            overdueBranchDays: count($overdueDates) * count($this->expectedBranches($department)),
            overdueFrom: $overdueDates[0] ?? null,
            overdueTo: $overdueDates === [] ? null : $overdueDates[array_key_last($overdueDates)],
        );
    }

    private function calendarFor(Department $department, ?CarbonImmutable $now = null): ReportingCalendar
    {
        return $this->calendar($department->company, $now);
    }

    /**
     * Branch and date of the active records in the range, plus the pending overlay. One query.
     *
     * @return list<array{branch_code: string, sales_date: string}>
     */
    private function activeRows(Department $department, CarbonImmutable $from, CarbonImmutable $to, ?PendingRows $pending): array
    {
        $rows = SalesRecord::query()
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->whereBetween('sales_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['branch_code', 'sales_date'])
            ->map(fn (SalesRecord $record) => [
                'branch_code' => $record->branch_code,
                'sales_date' => $record->sales_date->format('Y-m-d'),
            ])
            ->all();

        return [...$rows, ...($pending?->toRows() ?? [])];
    }

    /**
     * present / expected as a percentage with 4 decimals, round half up; null when nothing is expected.
     */
    private function percent(int $present, int $expected): ?string
    {
        if ($expected === 0) {
            return null;
        }

        // Dividing at a higher scale and adding half of the last place before truncating rounds half up.
        return bcadd(bcdiv(bcmul((string) $present, '100', 10), (string) $expected, 10), '0.00005', 4);
    }
}
