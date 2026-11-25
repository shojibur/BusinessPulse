<?php

namespace App\Services\Intelligence;

use App\Models\Department;
use App\Services\Imports\CoverageGap;
use App\Services\Imports\ReportingCalendar;
use App\Services\Imports\ReportingCoverage;
use App\Services\Imports\WeekState;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;

/**
 * The calendar facts of one evaluation run, built once from S03's ReportingCoverage and shared by the three
 * evaluators so they agree on dates. It reads no other clock and never reads freshness (freshness is a display
 * value and creates no issue).
 */
final readonly class EvaluationContext
{
    /**
     * @param  list<string>  $expectedBranches
     * @param  list<CarbonImmutable>  $dueDates  trading days of the missing-data window that are due
     * @param  list<CoverageGap>  $gaps  due branch-days of the window without an active record
     * @param  array{trading_days: list<int>, cutoff_time: string, cutoff_lag_days: int, timezone: string}  $calendar
     */
    public function __construct(
        public CarbonImmutable $now,
        public ?CarbonImmutable $asOf,
        public CarbonImmutable $lastDue,
        public WeekState $latest,
        public WeekState $preceding,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public array $gaps,
        public array $expectedBranches,
        public array $dueDates,
        public array $calendar,
        public bool $noRecords,
    ) {}

    public static function build(Department $department, ReportingCoverage $coverage, BusinessClock $clock): self
    {
        $company = $department->company;
        $now = $clock->now($company->timezone);
        $asOf = $coverage->latestSalesDate($department);
        $lastDue = $coverage->lastDueDate($department, $now);

        $latest = $coverage->latestClosedWeek($department, $now);
        $preceding = $coverage->weekState($department, $latest->weekStart->subDays(7), null, $now);

        // Monday of the ISO week before the week of today, through the last due date (inclusive).
        $windowStart = ReportingCalendar::weekStart($clock->today($company->timezone))->subDays(7);
        $gaps = $coverage->missingDueBranchDays($department, $windowStart, $lastDue, null, $now);

        // Due trading days of the window: the denominator of missing-data coverage (never a not-yet-due day).
        $calendar = $coverage->calendar($company, $now);
        $dueDates = [];
        for ($date = $windowStart; $date <= $lastDue; $date = $date->addDay()) {
            if ($calendar->isDue($date)) {
                $dueDates[] = $date;
            }
        }

        return new self(
            now: $now,
            asOf: $asOf,
            lastDue: $lastDue,
            latest: $latest,
            preceding: $preceding,
            windowStart: $windowStart,
            windowEnd: $lastDue,
            gaps: $gaps,
            expectedBranches: $coverage->expectedBranches($department),
            dueDates: $dueDates,
            calendar: [
                'trading_days' => $company->trading_days,
                'cutoff_time' => $company->reporting_cutoff_time,
                'cutoff_lag_days' => $company->reporting_cutoff_lag_days,
                'timezone' => $company->timezone,
            ],
            noRecords: $asOf === null,
        );
    }
}
