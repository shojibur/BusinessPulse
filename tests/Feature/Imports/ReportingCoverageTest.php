<?php

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Enums\FreshnessState;
use App\Enums\WeekCompleteness;
use App\Enums\WeekStatus;
use App\Models\Company;
use App\Models\SalesRecord;
use App\Services\Imports\CoverageGap;
use App\Services\Imports\PendingRows;
use App\Services\Imports\ReportingCalendar;
use App\Services\Imports\ReportingCoverage;
use App\Services\Imports\ValidRow;
use Carbon\CarbonImmutable;

const TZ = 'Asia/Kuala_Lumpur';

function at(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, TZ);
}

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'UTC');
}

function calendarAt(string $dateTime): ReportingCalendar
{
    return ReportingCalendar::forCompany(new Company([
        'timezone' => TZ, 'trading_days' => [1, 2, 3, 4, 5, 6], 'reporting_cutoff_time' => '12:00', 'reporting_cutoff_lag_days' => 1,
    ]), at($dateTime));
}

beforeEach(function () {
    $this->f = identity();
    $this->coverage = app(ReportingCoverage::class);
    $this->sales = $this->f->sales->fresh();
});

test('a trading day is due exactly when its cutoff (day plus lag at the cutoff time) has passed', function () {
    // Saturday 2026-09-26 is due from Sunday 2026-09-27 12:00.
    expect(calendarAt('2026-09-27 11:59')->isDue(day('2026-09-26')))->toBeFalse()
        ->and(calendarAt('2026-09-27 12:00')->isDue(day('2026-09-26')))->toBeTrue()
        ->and(calendarAt('2026-09-27 12:00')->isDue(day('2026-09-27')))->toBeFalse()  // a Sunday is never a trading day
        ->and(calendarAt('2026-09-28 09:00')->isDue(day('2026-09-28')))->toBeFalse()  // today is not due yet
        ->and(calendarAt('2026-09-28 09:00')->isDue(day('2026-09-30')))->toBeFalse()  // the future is never due
        ->and(calendarAt('2026-09-29 12:00')->isDue(day('2026-09-28')))->toBeTrue();
});

test('the last due date skips non trading days and honours the cutoff time', function () {
    expect(calendarAt('2026-09-28 09:00')->lastDueDate()->format('Y-m-d'))->toBe('2026-09-26')
        ->and(calendarAt('2026-09-27 11:59')->lastDueDate()->format('Y-m-d'))->toBe('2026-09-25')
        ->and(calendarAt('2026-09-27 12:00')->lastDueDate()->format('Y-m-d'))->toBe('2026-09-26')
        ->and(calendarAt('2026-09-28 12:00')->lastDueDate()->format('Y-m-d'))->toBe('2026-09-26')
        ->and(calendarAt('2026-09-29 12:00')->lastDueDate()->format('Y-m-d'))->toBe('2026-09-28');
});

test('a week is closed only once every trading day is due', function () {
    expect(calendarAt('2026-09-27 11:59')->isWeekClosed(day('2026-09-21')))->toBeFalse()
        ->and(calendarAt('2026-09-27 12:00')->isWeekClosed(day('2026-09-24')))->toBeTrue()
        ->and(calendarAt('2026-09-21 09:00')->isWeekClosed(day('2026-09-21')))->toBeFalse()
        ->and(count(calendarAt('2026-09-21 09:00')->tradingDates(day('2026-09-23'))))->toBe(6)
        ->and(calendarAt('2026-09-21 09:00')->tradingDates(day('2026-09-23'))[0]->format('Y-m-d'))->toBe('2026-09-21');
});

test('the cutoff uses the company timezone, not the server timezone', function () {
    $utc = CarbonImmutable::parse('2026-09-27 04:00', 'UTC'); // 12:00 in Kuala Lumpur
    $calendar = ReportingCalendar::forCompany(new Company([
        'timezone' => TZ, 'trading_days' => [1, 2, 3, 4, 5, 6], 'reporting_cutoff_time' => '12:00', 'reporting_cutoff_lag_days' => 1,
    ]), $utc);

    expect($calendar->isDue(day('2026-09-26')))->toBeTrue()
        ->and(ReportingCalendar::forCompany(new Company([
            'timezone' => TZ, 'trading_days' => [1, 2, 3, 4, 5, 6], 'reporting_cutoff_time' => '12:00', 'reporting_cutoff_lag_days' => 1,
        ]), $utc->subMinute())->isDue(day('2026-09-26')))->toBeFalse();
});

test('Monday of week 8 is not due and nothing is missing before the data is due (clock 21 Sep 09:00)', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    $now = at('2026-09-21 09:00');

    $w8 = $this->coverage->weekState($this->sales, day('2026-09-21'), null, $now);
    $freshness = $this->coverage->freshness($this->sales, null, $now);

    expect($w8->status)->toBe(WeekStatus::Open)->and($w8->completeness)->toBeNull()
        ->and($w8->gaps)->toBe([])->and($w8->dueBranchDays)->toBe(0)
        ->and($this->coverage->missingDueBranchDays($this->sales, day('2026-09-21'), day('2026-09-26'), null, $now))->toBe([])
        ->and($freshness->state)->toBe(FreshnessState::Current)
        ->and($freshness->latestSalesDate->format('Y-m-d'))->toBe('2026-09-19')
        ->and($freshness->lastDueDate->format('Y-m-d'))->toBe('2026-09-19')
        ->and($freshness->overdueBranchDays)->toBe(0)
        ->and($this->coverage->weekState($this->sales, day('2026-09-14'), null, $now)->completeness)->toBe(WeekCompleteness::Complete);
});

test('trailing due days are reported at 28 Sep 09:00: week 8 is closed and absent, 18 branch-days are overdue', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    $now = at('2026-09-28 09:00');

    $w8 = $this->coverage->weekState($this->sales, day('2026-09-21'), null, $now);
    $freshness = $this->coverage->freshness($this->sales, null, $now);
    $gaps = $this->coverage->missingDueBranchDays($this->sales, day('2026-09-21'), day('2026-10-31'), null, $now);

    expect($w8->status)->toBe(WeekStatus::Closed)->and($w8->completeness)->toBe(WeekCompleteness::Absent)
        ->and($w8->presentBranchDays)->toBe(0)->and($w8->expectedBranchDays)->toBe(18)->and($w8->coveragePct)->toBe('0.0000')
        ->and($freshness->state)->toBe(FreshnessState::Behind)
        ->and($freshness->latestSalesDate->format('Y-m-d'))->toBe('2026-09-19')
        ->and($freshness->lastDueDate->format('Y-m-d'))->toBe('2026-09-26')
        ->and($freshness->overdueBranchDays)->toBe(18)
        ->and([$freshness->overdueFrom->format('Y-m-d'), $freshness->overdueTo->format('Y-m-d')])->toBe(['2026-09-21', '2026-09-26'])
        // The window reaches into the future, but no gap is later than the last due date, and Monday 28 Sep is not due.
        ->and($gaps)->toHaveCount(18)
        ->and(collect($gaps)->every(fn (CoverageGap $gap) => $gap->kind === CoverageGap::AFTER_LATEST && $gap->date->format('Y-m-d') <= '2026-09-26'))->toBeTrue()
        ->and($this->coverage->isDue($this->sales, day('2026-09-26'), $now))->toBeTrue()
        ->and($this->coverage->isDue($this->sales, day('2026-09-28'), $now))->toBeFalse()
        ->and($freshness->overdueBranchDays)->toBe(collect($gaps)->where('kind', CoverageGap::AFTER_LATEST)->count());
});

test('an open week only lists gaps for days whose cutoff has passed', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');

    // Wed 23 Sep 09:00: Monday (cutoff Tue 12:00) is due; Tuesday (cutoff Wed 12:00) is not.
    $w8 = $this->coverage->weekState($this->sales, day('2026-09-21'), null, at('2026-09-23 09:00'));
    expect($w8->status)->toBe(WeekStatus::Open)->and($w8->completeness)->toBeNull()
        ->and($w8->dueBranchDays)->toBe(3)
        ->and(collect($w8->gaps)->pluck('date')->map->format('Y-m-d')->unique()->values()->all())->toBe(['2026-09-21']);

    // At 12:00 sharp Tuesday joins.
    $later = $this->coverage->weekState($this->sales, day('2026-09-21'), null, at('2026-09-23 12:00'));
    expect($later->dueBranchDays)->toBe(6);
});

test('a missing row inside the submitted period makes the closed week incomplete with one inside_period gap', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    importFixture($this->f->owner, 'week8-with-errors', '2026-09-28 09:00');
    $now = at('2026-09-28 09:00');

    $w8 = $this->coverage->weekState($this->sales, day('2026-09-21'), null, $now);
    $gaps = $this->coverage->missingDueBranchDays($this->sales, day('2026-09-21'), day('2026-09-26'), null, $now);

    expect($w8->status)->toBe(WeekStatus::Closed)->and($w8->completeness)->toBe(WeekCompleteness::Incomplete)
        ->and($w8->presentBranchDays)->toBe(17)->and($w8->coveragePct)->toBe('94.4444')
        ->and($w8->gaps)->toHaveCount(1)
        ->and([$w8->gaps[0]->branchCode, $w8->gaps[0]->date->format('Y-m-d'), $w8->gaps[0]->kind])->toBe(['JB', '2026-09-26', 'inside_period'])
        ->and($gaps)->toHaveCount(1)
        ->and($gaps[0]->kind)->toBe(CoverageGap::INSIDE_PERIOD)
        // The gap is not a zero: no stored row exists for it, and the week sum is the sum of the 17 valid rows only.
        ->and(SalesRecord::where('branch_code', 'JB')->where('sales_date', '2026-09-26')->count())->toBe(0)
        ->and(activeRevenue())->toBe(bcadd('1064000.00', '119231.00', 2));

    // Freshness is current: the latest sales date (26 Sep) reaches the last due date.
    expect($this->coverage->freshness($this->sales, null, $now)->state)->toBe(FreshnessState::Current);
});

test('a zero revenue row counts as present and a gap is never zero filled', function () {
    $calendar = calendarAt('2026-09-28 09:00');
    $branches = ['JB', 'KL', 'PG'];
    $rows = [];
    foreach (['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26'] as $date) {
        foreach ($branches as $branch) {
            $rows[] = ['branch_code' => $branch, 'sales_date' => $date];
        }
    }

    // All 18 branch-days present (a zero-revenue day is still a row): complete.
    $complete = $this->coverage->weekStateForRows($calendar, $branches, day('2026-09-21'), $rows);
    expect($complete->completeness)->toBe(WeekCompleteness::Complete)->and($complete->coveragePct)->toBe('100.0000');

    // Remove one branch-day: incomplete; remove all: absent.
    $incomplete = $this->coverage->weekStateForRows($calendar, $branches, day('2026-09-21'), array_slice($rows, 1));
    expect($incomplete->completeness)->toBe(WeekCompleteness::Incomplete)->and($incomplete->gaps)->toHaveCount(1);
    expect($this->coverage->weekStateForRows($calendar, $branches, day('2026-09-21'), [])->completeness)->toBe(WeekCompleteness::Absent);

    // An imported 0.00 revenue is stored and counted as present.
    $import = app(CreateImportFromUpload::class)->handle($this->f->owner, csvUpload("sales_date,branch_code,revenue,target_revenue,order_count\n2026-09-21,KL,0.00,12000.00,\n"));
    app(ConfirmImport::class)->handle($import);
    expect(SalesRecord::where('branch_code', 'KL')->value('revenue'))->toBe('0.00')
        ->and($this->coverage->weekState($this->sales, day('2026-09-21'), null, at('2026-09-28 09:00'))->presentBranchDays)->toBe(1);
});

test('completeness invariant: complete if and only if every expected branch-day is present', function () {
    $calendar = calendarAt('2026-09-28 09:00');
    $branches = ['JB', 'KL', 'PG'];
    $all = [];
    foreach (['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26'] as $date) {
        foreach ($branches as $branch) {
            $all[] = ['branch_code' => $branch, 'sales_date' => $date];
        }
    }

    foreach (range(0, count($all)) as $removed) {
        $state = $this->coverage->weekStateForRows($calendar, $branches, day('2026-09-21'), array_slice($all, $removed));
        $isComplete = $state->completeness === WeekCompleteness::Complete;

        expect($isComplete)->toBe($state->presentBranchDays === $state->expectedBranchDays && $state->expectedBranchDays > 0);
    }

    // No expected branches: never complete, never a divide by zero.
    $none = $this->coverage->weekStateForRows($calendar, [], day('2026-09-21'), $all);
    expect($none->completeness)->toBe(WeekCompleteness::Absent)->and($none->coveragePct)->toBeNull()->and($none->expectedBranchDays)->toBe(0);
});

test('records on non trading days and for other branches are ignored when counting what is present', function () {
    $calendar = calendarAt('2026-09-28 09:00');
    $rows = [
        ['branch_code' => 'KL', 'sales_date' => '2026-09-27'], // Sunday
        ['branch_code' => 'XX', 'sales_date' => '2026-09-21'], // not expected
    ];

    $state = $this->coverage->weekStateForRows($calendar, ['KL'], day('2026-09-21'), $rows);

    expect($state->presentBranchDays)->toBe(0)->and($state->completeness)->toBe(WeekCompleteness::Absent);
});

test('a pending overlay is evaluated as if saved and writes nothing', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    $now = at('2026-09-28 09:00');
    $pending = new PendingRows(array_map(
        fn (array $row) => new ValidRow(2, day($row[0]), $row[1], '1.00', '1.00', null),
        collect(['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26'])
            ->flatMap(fn ($date) => collect(['JB', 'KL', 'PG'])->map(fn ($branch) => [$date, $branch]))->all(),
    ));

    $withPending = $this->coverage->weekState($this->sales, day('2026-09-21'), $pending, $now);
    $freshness = $this->coverage->freshness($this->sales, $pending, $now);

    expect($withPending->completeness)->toBe(WeekCompleteness::Complete)
        ->and($freshness->state)->toBe(FreshnessState::Current)->and($freshness->overdueBranchDays)->toBe(0)
        ->and($this->coverage->weekState($this->sales, day('2026-09-21'), null, $now)->completeness)->toBe(WeekCompleteness::Absent)
        ->and(SalesRecord::count())->toBe(126);
});

test('with no records at all the department is behind with an unknown overdue count', function () {
    $freshness = $this->coverage->freshness($this->sales, null, at('2026-09-28 09:00'));

    expect($freshness->state)->toBe(FreshnessState::Behind)->and($freshness->latestSalesDate)->toBeNull()
        ->and($freshness->overdueBranchDays)->toBeNull();
});

test('the latest closed week is the week of the last due date, or the previous week', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');

    expect($this->coverage->latestClosedWeek($this->sales, at('2026-09-28 09:00'))->weekStart->format('Y-m-d'))->toBe('2026-09-21')
        ->and($this->coverage->latestClosedWeek($this->sales, at('2026-09-23 09:00'))->weekStart->format('Y-m-d'))->toBe('2026-09-14')
        ->and($this->coverage->latestClosedWeek($this->sales, at('2026-09-26 09:00'))->completeness)->toBe(WeekCompleteness::Complete);
});

test('coverage uses a bounded number of queries regardless of the number of rows', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    $now = at('2026-09-28 09:00');

    DB::enableQueryLog();
    $this->coverage->weekStates($this->sales->fresh(), day('2026-08-03'), day('2026-09-21'), null, $now);
    $weeksQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    $this->coverage->weekStates($this->sales->fresh(), day('2026-09-14'), day('2026-09-21'), null, $now);
    $fewQueries = count(DB::getQueryLog());

    expect($weeksQueries)->toBe($fewQueries)->and($weeksQueries)->toBeLessThanOrEqual(3);
});
