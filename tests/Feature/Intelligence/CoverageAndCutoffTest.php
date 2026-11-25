<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\InsufficientReason;
use App\Enums\RuleResult;
use App\Models\Issue;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

function evaluateNow(string $clock): void
{
    setClock($clock);
    app(DispatchEvaluation::class)->handle(test()->f->sales->id);
}

test('a day whose cutoff has not passed is never missing: the open week of the current clock is not in the window', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    evaluateNow('2026-09-28 09:00');

    $missing = latestEvaluation('missing_sales_data');
    $dates = collect($missing->evidence['coverage']['missingDueBranchDays'])->pluck('date')->unique()->sort()->values()->all();

    expect($missing->period_start->format('Y-m-d'))->toBe('2026-09-21')->and($missing->period_end->format('Y-m-d'))->toBe('2026-09-26')
        ->and($dates)->toBe(['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26'])
        ->and(collect($missing->evidence['coverage']['missingDueBranchDays'])->every(fn ($gap) => $gap['date'] < '2026-09-28'))->toBeTrue();
});

test('due trailing days are reported: nothing arrived for week 8 by 28 Sep, so 0 of 18 and absent money weeks', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    evaluateNow('2026-09-28 09:00');

    $missing = latestEvaluation('missing_sales_data');
    expect($missing->result)->toBe(RuleResult::Breached)->and((int) $missing->current_value)->toBe(0)->and((int) $missing->comparison_value)->toBe(18)
        ->and($missing->difference_pct)->toBe('0.0000')
        ->and(collect($missing->evidence['coverage']['missingDueBranchDays'])->pluck('kind')->unique()->all())->toBe(['after_latest'])
        ->and(latestEvaluation('weekly_revenue_decline')->insufficient_reason)->toBe(InsufficientReason::PeriodAbsent)
        ->and(latestEvaluation('sales_target_shortfall')->insufficient_reason)->toBe(InsufficientReason::PeriodAbsent);
});

test('the cutoff boundary decides which days count: 11:59 versus 12:00 on Sunday after the week', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');

    // 27 Sep 11:59: Saturday 26 Sep is not due yet. Last due date 25 Sep, week 8 still open, latest closed week is W7.
    evaluateNow('2026-09-27 11:59');
    $before = latestEvaluation('missing_sales_data');
    $decline = latestEvaluation('weekly_revenue_decline');

    // Window 14 Sep to 25 Sep: 11 trading days x 3 branches = 33 expected; the 18 present are all of week 7.
    expect($before->period_end->format('Y-m-d'))->toBe('2026-09-25')->and($before->last_due_date->format('Y-m-d'))->toBe('2026-09-25')
        ->and((int) $before->comparison_value)->toBe(33)->and((int) $before->current_value)->toBe(18)
        ->and($decline->period_start->format('Y-m-d'))->toBe('2026-09-14') // week 7 is still the latest CLOSED week
        ->and($decline->result)->toBe(RuleResult::NotBreached);

    // 27 Sep 12:00: Saturday 26 Sep becomes due, week 8 closes and is absent.
    evaluateNow('2026-09-27 12:00');
    $after = latestEvaluation('missing_sales_data');
    expect($after->last_due_date->format('Y-m-d'))->toBe('2026-09-26')->and((int) $after->comparison_value)->toBe(36)
        ->and(latestEvaluation('weekly_revenue_decline')->insufficient_reason)->toBe(InsufficientReason::PeriodAbsent);
});

test('the denominator counts only due trading days of the window', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');

    // Wednesday 30 Sep 09:00: window = 21 Sep to 29 Sep... last due date is Tuesday 29 Sep? Tuesday's cutoff is Wed 12:00,
    // so the last due date is Monday 28 Sep: due days 21-26 and 28 Sep (7 days) x 3 branches.
    evaluateNow('2026-09-30 09:00');

    $missing = latestEvaluation('missing_sales_data');
    expect($missing->last_due_date->format('Y-m-d'))->toBe('2026-09-28')
        ->and((int) $missing->comparison_value)->toBe(21)
        ->and($missing->evidence['periods']['window']['dueDates'])->toBe(['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-28']);
});

test('an incomplete closed week gives incomplete_period and a coverage breach of 17 of 18', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    importFixture($this->owner, 'week8-with-errors', '2026-09-28 09:00');

    expect(latestEvaluation('weekly_revenue_decline')->insufficient_reason)->toBe(InsufficientReason::IncompletePeriod)
        ->and(latestEvaluation('sales_target_shortfall')->insufficient_reason)->toBe(InsufficientReason::IncompletePeriod);

    $missing = latestEvaluation('missing_sales_data');
    expect((int) $missing->current_value)->toBe(17)->and((int) $missing->comparison_value)->toBe(18)
        ->and(Issue::where('title', 'Weekly revenue decline')->count())->toBe(0);
});

test('an incomplete comparison week also blocks the decline rule', function () {
    // Week 7 misses one branch-day, week 8 is complete and sharply lower.
    setClock('2026-09-28 09:00');
    importCsv($this->owner, collect(explode("\n", trim(weekCsv('2026-09-14', '1000.00'))))->slice(1)->implode("\n")."\n"); // drops the first KL row
    importCsv($this->owner, weekCsv('2026-09-21', '100.00'));

    $decline = latestEvaluation('weekly_revenue_decline');
    expect($decline->result)->toBe(RuleResult::InsufficientData)->and($decline->insufficient_reason)->toBe(InsufficientReason::IncompletePeriod)
        ->and(Issue::where('title', 'Weekly revenue decline')->count())->toBe(0);
});

test('freshness alone never creates an issue', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');

    // Latest sales date is behind the last due date but no rule treats that as a finding by itself:
    // only the missing data rule can, through due branch-days (and here the clock has not made any due).
    evaluateNow('2026-09-21 12:00');

    expect(Issue::count())->toBe(0)
        ->and(latestEvaluation('missing_sales_data')->result)->toBe(RuleResult::NotBreached);
});
