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

test('an incomplete closed week gives incomplete_period and a coverage breach of 17 of 18', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    importFixture($this->owner, 'week8-with-errors', '2026-09-28 09:00');

    expect(latestEvaluation('weekly_revenue_decline')->insufficient_reason)->toBe(InsufficientReason::IncompletePeriod)
        ->and(latestEvaluation('sales_target_shortfall')->insufficient_reason)->toBe(InsufficientReason::IncompletePeriod);

    $missing = latestEvaluation('missing_sales_data');
    expect((int) $missing->current_value)->toBe(17)->and((int) $missing->comparison_value)->toBe(18)
        ->and(Issue::where('title', 'Weekly revenue decline')->count())->toBe(0);
});
