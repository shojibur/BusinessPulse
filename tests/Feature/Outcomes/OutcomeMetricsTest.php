<?php

require_once __DIR__.'/helpers.php';

use App\Enums\IssueStatus;
use App\Enums\OutcomeMetric;
use App\Enums\OutcomeResult;
use App\Enums\RuleResult;
use App\Models\RuleEvaluation;
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

function evaluationFor(array $attributes, array $evidence = []): RuleEvaluation
{
    $e = new RuleEvaluation($attributes);
    $e->evidence = $evidence + ['coverage' => ['expectedBranches' => ['KL', 'PG', 'JB']], 'thresholdUsed' => '15.00', 'contributingImportIds' => []];
    $e->result = $attributes['result'] ?? RuleResult::Breached;

    return $e;
}

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
