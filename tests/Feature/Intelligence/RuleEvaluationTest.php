<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleResult;
use App\Models\Issue;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

// Default rules of the plain fixture: decline threshold 15 (high at 25), target threshold 80 (high below 70).

function evaluateAt(string $clock): void
{
    setClock($clock);
    app(DispatchEvaluation::class)->handle(test()->f->sales->id);
}

test('a drop of exactly the threshold is not breached; any drop beyond it is', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00'));   // 18,000.00
    importCsv($this->owner, weekCsv('2026-09-21', '850.00'));    // 15,300.00 = exactly -15.00%

    $evaluation = latestEvaluation('weekly_revenue_decline');
    expect($evaluation->result)->toBe(RuleResult::NotBreached)->and($evaluation->difference_pct)->toBe('-15.0000')
        ->and(Issue::where('title', 'Weekly revenue decline')->count())->toBe(0);

    importCsv($this->owner, weekCsv('2026-09-21', '849.99'));    // corrected: -15.0010%

    $evaluation = latestEvaluation('weekly_revenue_decline');
    expect($evaluation->result)->toBe(RuleResult::Breached)->and($evaluation->severity)->toBe(IssueSeverity::Medium)
        ->and($evaluation->difference_pct)->toBe('-15.0010');
});

test('decline severity is high from high_drop_pct and medium below it, and growth never breaches', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '750.00')); // exactly -25.00%

    expect(latestEvaluation('weekly_revenue_decline')->severity)->toBe(IssueSeverity::High);

    importCsv($this->owner, weekCsv('2026-09-21', '1200.00')); // grew
    expect(latestEvaluation('weekly_revenue_decline')->result)->toBe(RuleResult::NotBreached);
});

test('target shortfall is strict at the threshold and high strictly below high_below_pct', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '5000.00', '5000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '4000.00', '5000.00')); // exactly 80.00%

    expect(latestEvaluation('sales_target_shortfall')->result)->toBe(RuleResult::NotBreached)
        ->and(latestEvaluation('sales_target_shortfall')->difference_pct)->toBe('80.0000');

    importCsv($this->owner, weekCsv('2026-09-21', '3999.99', '5000.00'));
    expect(latestEvaluation('sales_target_shortfall')->result)->toBe(RuleResult::Breached)
        ->and(latestEvaluation('sales_target_shortfall')->severity)->toBe(IssueSeverity::Medium);

    importCsv($this->owner, weekCsv('2026-09-21', '3500.00', '5000.00')); // exactly 70.00%: not strictly below
    expect(latestEvaluation('sales_target_shortfall')->severity)->toBe(IssueSeverity::Medium);

    importCsv($this->owner, weekCsv('2026-09-21', '3499.99', '5000.00'));
    expect(latestEvaluation('sales_target_shortfall')->severity)->toBe(IssueSeverity::High);
});

test('a comparison week with zero revenue or a week with zero target is a zero denominator, not a finding', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '0.00', '0.00')); // present rows with real zeros
    importCsv($this->owner, weekCsv('2026-09-21', '1000.00', '5000.00'));

    $decline = latestEvaluation('weekly_revenue_decline');
    expect($decline->result)->toBe(RuleResult::InsufficientData)->and($decline->insufficient_reason)->toBe(InsufficientReason::ZeroDenominator)
        ->and($decline->difference_pct)->toBeNull();

    // Zero target in the evaluated week.
    importCsv($this->owner, weekCsv('2026-09-21', '1000.00', '0.00'));
    $target = latestEvaluation('sales_target_shortfall');
    expect($target->result)->toBe(RuleResult::InsufficientData)->and($target->insufficient_reason)->toBe(InsufficientReason::ZeroDenominator)
        // The earlier 20% achievement opened a target issue; the zero-target evaluation does not touch it.
        ->and(Issue::where('title', 'Weekly revenue decline')->count())->toBe(0)
        ->and(Issue::where('title', 'Sales target shortfall')->firstOrFail()->detection_count)->toBe(1);
});

test('zero revenue in the evaluated week is a real value: 0% achievement breaches', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '0.00', '5000.00'));

    $target = latestEvaluation('sales_target_shortfall');
    expect($target->result)->toBe(RuleResult::Breached)->and($target->severity)->toBe(IssueSeverity::High)->and($target->difference_pct)->toBe('0.0000');

    $decline = latestEvaluation('weekly_revenue_decline');
    expect($decline->difference_pct)->toBe('-100.0000')->and($decline->severity)->toBe(IssueSeverity::High);
});

test('a department with no sales records gets no_records for every rule and no issue', function () {
    evaluateAt('2026-09-28 09:00');

    foreach (['weekly_revenue_decline', 'sales_target_shortfall', 'missing_sales_data'] as $key) {
        $evaluation = latestEvaluation($key);
        expect($evaluation->result)->toBe(RuleResult::InsufficientData)->and($evaluation->insufficient_reason)->toBe(InsufficientReason::NoRecords)
            ->and($evaluation->as_of_date)->toBeNull();
    }

    expect(Issue::count())->toBe(0);
});
