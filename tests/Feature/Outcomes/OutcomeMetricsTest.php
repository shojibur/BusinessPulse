<?php

require_once __DIR__.'/helpers.php';

use App\Enums\IssueStatus;
use App\Enums\OutcomeMetric;
use App\Enums\OutcomeResult;
use App\Enums\RuleResult;
use App\Models\RuleEvaluation;
use App\Services\Outcomes\ComparabilityAssessor;
use App\Services\Outcomes\OutcomeMetricCalculator;

test('the classification bands are inclusive at +/-2.00 and negative zero is neutral', function (string $change, OutcomeResult $expected) {
    expect(app(OutcomeMetricCalculator::class)->classify($change))->toBe($expected);
})->with([
    ['2.00', OutcomeResult::Improved], ['1.99', OutcomeResult::Unchanged], ['0.00', OutcomeResult::Unchanged], ['-0.00', OutcomeResult::Unchanged],
    ['-1.99', OutcomeResult::Unchanged], ['-2.00', OutcomeResult::Worsened], ['-2.01', OutcomeResult::Worsened], ['20.97', OutcomeResult::Improved],
]);

test('target achievement improves by percentage points of the displayed values: 79.49 to 96.15 is +16.66', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');

    $o = latestOutcome($x->target);
    expect($o->outcome_metric)->toBe(OutcomeMetric::TargetAchievement)->and($o->baseline_value)->toBe('79.49')->and($o->followup_value)->toBe('96.15')
        ->and($o->point_change)->toBe('16.66')->and($o->relative_change_pct)->toBeNull()->and($o->absolute_change)->toBeNull()->and($o->reference_value)->toBeNull()
        ->and($o->result->value)->toBe('improved')->and($o->phase->value)->toBe('post_action')->and($o->resolution_condition_met)->toBeTrue()
        ->and($x->target->fresh()->status)->toBe(IssueStatus::Resolved);
});

test('growth is not recovery: a rebound week below the pre-decline level never meets the condition', function () {
    $x = monitoringScenario();
    // W9 120,000 then W10 128,000: +6.67% week over week and not breached, but 128,000 < 131,750.
    setClock('2026-10-05 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-09-28', '120000.00'));
    setClock('2026-10-12 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-10-05', '128000.00'));

    $o = latestOutcome($x->decline);
    expect($o->phase->value)->toBe('post_action')->and($o->followup_value)->toBe('128000.00')->and($o->relative_change_pct)->toBe('3.23')
        ->and($o->resolution_condition_met)->toBeFalse()->and($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring)
        ->and($o->evidence['weeklyChangeEvidence']['followupPct4'])->toBe('6.6667');
});

test('values are decimal strings stored in the dedicated columns, never the week-over-week change', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    $o = latestOutcome($x->decline);

    expect($o->getAttributes()['baseline_value'])->toBeString()->and($o->relative_change_pct)->not->toBe($o->evidence['weeklyChangeEvidence']['followupPct4'])
        ->and(array_keys($o->evidence))->toContain('weeklyChangeEvidence', 'recoveredSharePct', 'checks', 'condition', 'values');
});

function evaluationFor(array $attributes, array $evidence = []): RuleEvaluation
{
    $e = new RuleEvaluation($attributes);
    $e->evidence = $evidence + ['coverage' => ['expectedBranches' => ['KL', 'PG', 'JB']], 'thresholdUsed' => '15.00', 'contributingImportIds' => []];
    $e->result = $attributes['result'] ?? RuleResult::Breached;

    return $e;
}

test('comparability checks run in order, stop at the first failure and never substitute zero', function () {
    $x = monitoringScenario();
    $assessor = app(ComparabilityAssessor::class);
    $mk = fn (array $a, array $ev = []) => evaluationFor($a + ['period_start' => '2026-09-21', 'period_end' => '2026-09-27', 'current_value' => '124000.00', 'comparison_value' => '155000.00'], $ev);
    $baseline = $mk(['result' => RuleResult::Breached]);

    $later = $mk(['period_start' => '2026-10-05', 'period_end' => '2026-10-10', 'result' => RuleResult::NotBreached]);
    expect($assessor->assess($x->department, OutcomeMetric::WeeklyRevenue, $mk(['result' => RuleResult::InsufficientData]), $later)->failedReason->value)->toBe('baseline_insufficient_data');
    expect($assessor->assess($x->department, OutcomeMetric::WeeklyRevenue, $baseline, $mk(['result' => RuleResult::NotBreached]))->failedReason->value)->toBe('followup_not_after_baseline');
    expect($assessor->assess($x->department, OutcomeMetric::WeeklyRevenue, $baseline, $mk(['period_start' => '2026-10-05', 'period_end' => '2026-10-10', 'result' => RuleResult::InsufficientData]))->failedReason->value)->toBe('followup_insufficient_data');

    $changed = $mk(['period_start' => '2026-10-05', 'period_end' => '2026-10-10', 'result' => RuleResult::NotBreached], ['coverage' => ['expectedBranches' => ['KL', 'PG']]]);
    setClock('2026-10-12 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-10-05', '150000.00')); // a complete closed W10 exists
    $assessment = $assessor->assess($x->department, OutcomeMetric::WeeklyRevenue, $baseline, $changed);
    expect($assessment->failedReason->value)->toBe('branches_changed')->and(collect($assessment->checks)->pluck('state')->all())->toBe(['passed', 'passed', 'passed', 'passed', 'failed', 'skipped']);

    $zero = $mk(['current_value' => '0.00', 'result' => RuleResult::Breached]);
    $ok = $mk(['period_start' => '2026-10-05', 'period_end' => '2026-10-10', 'result' => RuleResult::NotBreached]);
    expect($assessor->assess($x->department, OutcomeMetric::WeeklyRevenue, $zero, $ok)->failedReason->value)->toBe('zero_baseline_value');
    // The same zero baseline is fine for a percentage metric, and coverage ignores week completeness.
    expect($assessor->assess($x->department, OutcomeMetric::Coverage, $zero, $ok)->comparable())->toBeTrue()
        ->and(collect($assessor->assess($x->department, OutcomeMetric::Coverage, $zero, $ok)->checks)->firstWhere('name', 'followup_week_complete')['state'])->toBe('not_applicable');
});

test('an incomplete follow-up week is not evaluable: no values, no change figures, no lifecycle call', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    setClock('2026-10-12 09:00');
    $rows = collect(explode("\n", trim(weekRowsForTotal('2026-10-05', '150000.00'))))->reject(fn ($r) => str_contains($r, '2026-10-10,JB'))->implode("\n")."\n";
    importCsv($x->owner, $rows);

    $o = latestOutcome($x->decline);
    expect($o->result)->toBe(OutcomeResult::NotEvaluable)->and($o->not_evaluable_reason->value)->toBe('followup_insufficient_data')->and($o->comparable)->toBeFalse()
        ->and([$o->baseline_value, $o->followup_value, $o->reference_value, $o->absolute_change, $o->relative_change_pct, $o->point_change])->each->toBeNull()
        ->and($o->resolution_condition_met)->toBeFalse()->and($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring)
        ->and($o->evidence['values'])->toBeNull();
});

test('a zero baseline never divides by zero and the database rejects an inconsistent outcome', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    $o = latestOutcome($x->decline);
    $row = collect($o->getAttributes())->except(['id'])->all();
    $row['evidence'] = json_encode($o->evidence);

    // resolution met on a non post-action row, a not_evaluable row with values, and a revenue row without its change fields.
    dbRejects(fn () => DB::table('outcomes')->insert([...$row, 'followup_evaluation_id' => $o->baseline_evaluation_id, 'resolution_condition_met' => true]));
    dbRejects(fn () => DB::table('outcomes')->insert([...$row, 'followup_evaluation_id' => $o->baseline_evaluation_id, 'result' => 'not_evaluable', 'not_evaluable_reason' => 'branches_changed', 'comparable' => false]));
    dbRejects(fn () => DB::table('outcomes')->insert([...$row, 'followup_evaluation_id' => $o->baseline_evaluation_id, 'absolute_change' => null]));
});
