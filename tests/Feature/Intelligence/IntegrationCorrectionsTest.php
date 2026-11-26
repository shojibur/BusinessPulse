<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\RuleKey;
use App\Models\Issue;
use App\Services\Intelligence\EvaluationHealth;
use App\Services\Intelligence\Evaluators\MissingSalesDataEvaluator;
use App\Services\Intelligence\Evaluators\RuleEvaluator;
use App\Services\Intelligence\Evaluators\SalesTargetShortfallEvaluator;
use App\Services\Intelligence\Evaluators\WeeklyRevenueDeclineEvaluator;
use App\Services\Intelligence\IssueEvidence;
use App\Services\Intelligence\MetricCalculator;
use Carbon\CarbonImmutable;

// S05 corrections to S04 integration points: aggregation scope, evaluation health, current vs historical evidence.

test('weekly totals ignore records on non-trading days and for non-expected branches', function () {
    $f = identity();
    $metrics = app(MetricCalculator::class);
    $monday = CarbonImmutable::parse('2026-09-14', 'UTC');

    importCsv($f->owner, weekCsv('2026-09-14', '1000.00'));
    // A Sunday (non-trading) row and a branch that is not expected: both are stored but never count.
    DB::table('sales_records')->where('is_active', true)->limit(1)->get(); // fixture rows exist
    $template = DB::table('sales_records')->where('is_active', true)->first();
    foreach ([['branch_code' => 'KL', 'sales_date' => '2026-09-20'], ['branch_code' => 'ZZ', 'sales_date' => '2026-09-15']] as $extra) {
        DB::table('sales_records')->insert(array_merge(array_diff_key((array) $template, ['id' => 1]), $extra));
    }

    $unrestricted = $metrics->weekTotals($f->sales->id, $monday);
    $scoped = $metrics->weekTotals($f->sales->id, $monday, [1, 2, 3, 4, 5, 6], ['KL', 'PG', 'JB']);

    expect($unrestricted->revenue)->toBe('20000.00')->and($scoped->revenue)->toBe('18000.00')->and($scoped->recordIds)->toHaveCount(18);
});

test('evaluation health distinguishes current, partial, failed and stale', function () {
    $owner = demoOwner();
    $department = demoDepartment();
    $health = app(EvaluationHealth::class);

    expect($health->forDepartment($department)['status'])->toBe('never');

    importFixture($owner, 'history', '2026-09-21 09:00');
    expect($health->forDepartment($department)['status'])->toBe('current');

    // The clock moves past the next cutoff: the last due date changed, so the evaluation is stale.
    setClock('2026-09-28 09:00');
    expect($health->forDepartment($department)['status'])->toBe('stale');
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    expect($health->forDepartment($department)['status'])->toBe('current');

    // One rule throws: partial, and the timestamp still moves for the rules that did run.
    $throwing = new class implements RuleEvaluator
    {
        public function key(): RuleKey
        {
            return RuleKey::WeeklyRevenueDecline;
        }

        public function evaluate($rule, $department, $context): never
        {
            throw new RuntimeException('boom');
        }
    };
    app()->bind(WeeklyRevenueDeclineEvaluator::class, fn () => $throwing);
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    $partial = $health->forDepartment($department);
    expect($partial['status'])->toBe('partial')->and($partial['failed_rules'])->toBe(1);

    // Every rule throws: failed.
    app()->bind(SalesTargetShortfallEvaluator::class, fn () => $throwing);
    app()->bind(MissingSalesDataEvaluator::class, fn () => $throwing);
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    expect($health->forDepartment($department)['status'])->toBe('failed');

    // Recovery.
    app()->forgetInstance(WeeklyRevenueDeclineEvaluator::class);
    app()->bind(WeeklyRevenueDeclineEvaluator::class);
    app()->bind(SalesTargetShortfallEvaluator::class);
    app()->bind(MissingSalesDataEvaluator::class);
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    expect($health->forDepartment($department)['status'])->toBe('current');
});

test('issue evidence separates the latest breach from the current condition', function () {
    $owner = declineTimeline();
    $issue = Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'sales_target_shortfall'))->firstOrFail();

    $evidence = IssueEvidence::forIssue($issue);
    expect($evidence->conditionStillBreached())->toBeTrue()->and($evidence->current->evaluationId)->toBe($issue->latest_evaluation_id);

    // A correction lifts revenue to 80.77% of target: the shortfall rule no longer breaches, but the issue keeps its recorded breach.
    importFixture($owner, 'week8-correction');
    $issue->refresh();
    $after = IssueEvidence::forIssue($issue);
    expect($after->latest->evaluationId)->toBe($issue->latest_evaluation_id)
        ->and($after->current->evaluationId)->toBeGreaterThan($after->latest->evaluationId)
        ->and($after->conditionStillBreached())->toBeFalse();
});
