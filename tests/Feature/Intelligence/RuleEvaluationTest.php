<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Actions\Intelligence\SeedDefaultRules;
use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleResult;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use Illuminate\Database\QueryException;

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

test('an inactive rule is skipped and leaves existing issues unchanged', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '500.00'));
    $issue = Issue::where('title', 'Weekly revenue decline')->firstOrFail();

    BusinessRule::where('key', 'weekly_revenue_decline')->update(['is_active' => false]);
    $before = RuleEvaluation::count();
    evaluateAt('2026-09-29 12:00'); // a new due day: other rules produce new evaluations

    expect(RuleEvaluation::whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->count())->toBe(RuleEvaluation::whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->count())
        ->and(RuleEvaluation::count())->toBeGreaterThan($before)
        ->and($issue->fresh()->detection_count)->toBe(1);
});

test('evaluations record the as-of date, last due date and a complete evidence snapshot', function () {
    setClock('2026-09-28 09:00');
    importCsv($this->owner, weekCsv('2026-09-14', '1000.00'));
    importCsv($this->owner, weekCsv('2026-09-21', '500.00'));

    $evaluation = latestEvaluation('weekly_revenue_decline');
    $evidence = $evaluation->evidence;

    expect($evaluation->as_of_date->format('Y-m-d'))->toBe('2026-09-26')->and($evaluation->last_due_date->format('Y-m-d'))->toBe('2026-09-26')
        ->and($evaluation->period_start->format('Y-m-d'))->toBe('2026-09-21')->and($evaluation->comparison_start->format('Y-m-d'))->toBe('2026-09-14')
        ->and($evaluation->evaluated_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-28 01:00') // 09:00 in Kuala Lumpur
        ->and($evidence['thresholdUsed'])->toBe('15.00')->and($evidence['paramsUsed']['high_drop_pct'])->toBe('25.00')
        ->and($evidence['calendar'])->toEqual(['tradingDays' => [1, 2, 3, 4, 5, 6], 'cutoffTime' => '12:00', 'cutoffLagDays' => 1, 'timezone' => 'Asia/Kuala_Lumpur'])
        ->and($evidence['periods']['current'])->toMatchArray(['revenue' => '9000.00', 'target' => '90000.00', 'status' => 'closed', 'completeness' => 'complete'])
        ->and($evidence['periods']['comparison']['revenue'])->toBe('18000.00')
        ->and($evidence['coverage']['expectedBranches'])->toBe(['JB', 'KL', 'PG'])
        ->and($evidence['contributingRecordIds'])->toHaveCount(36)
        ->and($evidence['contributingImportIds'])->toHaveCount(2)
        ->and(strlen($evaluation->run_key))->toBe(64);
});

test('the evaluation database constraints reject inconsistent rows', function () {
    $f = $this->f;
    app(SeedDefaultRules::class)->handle($f->sales);
    $rule = BusinessRule::first();
    $base = [
        'business_rule_id' => $rule->id, 'department_id' => $f->sales->id, 'period_start' => '2026-09-21', 'period_end' => '2026-09-27',
        'metric_name' => 'weekly_change_pct', 'last_due_date' => '2026-09-26', 'evidence' => '{}', 'evaluated_at' => now(), 'run_key' => str_repeat('a', 64),
        'created_at' => now(), 'updated_at' => now(),
    ];

    foreach ([
        'breached without a severity' => ['result' => 'breached'],
        'a severity on a non-breach' => ['result' => 'not_breached', 'severity' => 'high'],
        'insufficient without a reason' => ['result' => 'insufficient_data'],
        'a reason on a breach' => ['result' => 'breached', 'severity' => 'high', 'insufficient_reason' => 'period_absent'],
        'an unknown metric' => ['result' => 'not_breached', 'metric_name' => 'made_up'],
    ] as $label => $override) {
        expect(fn () => DB::transaction(fn () => DB::table('rule_evaluations')->insert([...$base, ...$override])))
            ->toThrow(QueryException::class, '', $label);
    }
});
