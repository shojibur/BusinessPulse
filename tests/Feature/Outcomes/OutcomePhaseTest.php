<?php

require_once __DIR__.'/helpers.php';

use App\Enums\IssueStatus;
use App\Enums\OutcomePhase;
use App\Models\Outcome;
use App\Models\Task;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Outcomes\OutcomePhaseResolver;
use Carbon\CarbonImmutable;

test('the phase follows the action and monitoring dates, never the current time', function () {
    $r = new OutcomePhaseResolver;
    $tz = 'Asia/Kuala_Lumpur';
    $d = fn (string $s) => CarbonImmutable::parse($s, $tz);
    $action = $d('2026-09-28 09:00');
    $monitoring = $d('2026-09-30 09:00');

    expect($r->resolve(null, null, $d('2026-09-21'), $d('2026-09-27'), $tz))->toBe(OutcomePhase::PreAction)               // no action yet
        ->and($r->resolve($action, null, $d('2026-09-21'), $d('2026-09-27'), $tz))->toBe(OutcomePhase::PreAction)         // ends before the action date
        ->and($r->resolve($action, null, $d('2026-09-28'), $d('2026-10-03'), $tz))->toBe(OutcomePhase::DuringAction)      // monitoring not started
        ->and($r->resolve($action, $monitoring, $d('2026-09-28'), $d('2026-10-03'), $tz))->toBe(OutcomePhase::DuringAction) // overlaps both dates
        ->and($r->resolve($action, $monitoring, $d('2026-09-30'), $d('2026-10-05'), $tz))->toBe(OutcomePhase::DuringAction) // starts ON the monitoring day
        ->and($r->resolve($action, $monitoring, $d('2026-10-01'), $d('2026-10-07'), $tz))->toBe(OutcomePhase::PostAction)   // starts strictly after
        ->and($r->resolve($action, $monitoring, $d('2026-10-05'), $d('2026-10-10'), $tz))->toBe(OutcomePhase::PostAction);
});

test('week 9 is during action: informational, never resolves, issue stays in monitoring', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');

    $o = latestOutcome($x->decline);
    expect($o->phase)->toBe(OutcomePhase::DuringAction)->and($o->result->value)->toBe('improved')->and($o->resolution_condition_met)->toBeFalse()
        ->and($o->followup_value)->toBe('138000.00')->and($o->baseline_value)->toBe('124000.00')->and($o->reference_value)->toBe('155000.00')
        ->and($o->absolute_change)->toBe('14000.00')->and($o->relative_change_pct)->toBe('11.29')->and($o->evidence['recoveredSharePct'])->toBe('89.03')
        ->and($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring)->and($x->decline->fresh()->resolved_at)->toBeNull();
});

test('week 10 is post action: recovery verified from the stored revenue and the issue is resolved by the system', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');

    $o = latestOutcome($x->decline);
    $issue = $x->decline->fresh();
    expect($o->phase)->toBe(OutcomePhase::PostAction)->and($o->result->value)->toBe('improved')->and($o->resolution_condition_met)->toBeTrue()
        ->and($o->baseline_value)->toBe('124000.00')->and($o->followup_value)->toBe('150000.00')->and($o->absolute_change)->toBe('26000.00')
        ->and($o->relative_change_pct)->toBe('20.97')->and($o->evidence['recoveredSharePct'])->toBe('96.77')
        ->and($o->evidence['condition']['limit4'])->toBe('131750.0000')->and($o->evidence['condition']['met'])->toBeTrue()
        // The week-over-week change is evidence only.
        ->and($o->evidence['weeklyChangeEvidence']['baselinePct4'])->toBe('-20.0000')->and($o->evidence['weeklyChangeEvidence']['followupPct4'])->toBe('8.6957')
        ->and($issue->status)->toBe(IssueStatus::Resolved)->and($issue->resolution_type->value)->toBe('system_verified')->and($issue->resolved_by)->toBeNull()
        ->and(collect($issue->status_history)->last()['trigger'])->toBe('outcome_verified');
});

test('an unchanged or worsening post-action week never resolves; worsening reopens with the baseline intact', function () {
    // 125,000: +0.81% against the 124,000 baseline -> unchanged, and below the 131,750 limit.
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    setClock('2026-10-12 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-10-05', '125000.00'));

    $o = latestOutcome($x->decline);
    expect($o->phase)->toBe(OutcomePhase::PostAction)->and($o->result->value)->toBe('unchanged')->and($o->resolution_condition_met)->toBeFalse()
        ->and($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring);
});

test('a worsened post-action outcome reopens the issue and keeps the original baseline', function () {
    $x = monitoringScenario();
    $baselineId = $x->decline->baseline_evaluation_id;
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    setClock('2026-10-12 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-10-05', '120000.00'));

    $o = latestOutcome($x->decline);
    $issue = $x->decline->fresh();
    expect($o->phase)->toBe(OutcomePhase::PostAction)->and($o->result->value)->toBe('worsened')->and($o->relative_change_pct)->toBe('-3.23')
        ->and($issue->status)->toBe(IssueStatus::Open)->and($issue->baseline_evaluation_id)->toBe($baselineId)->and($issue->action_started_at)->toBeNull()->and($issue->monitoring_started_at)->toBeNull()
        ->and(collect($issue->status_history)->last()['trigger'])->toBe('outcome_worsened');
});

test('the resolution condition boundary: 131,750.00 resolves and 131,749.99 does not', function (string $total, bool $resolves) {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    setClock('2026-10-12 09:00');
    importCsv($x->owner, weekRowsForTotal('2026-10-05', $total));

    expect(latestOutcome($x->decline)->resolution_condition_met)->toBe($resolves)
        ->and($x->decline->fresh()->status === IssueStatus::Resolved)->toBe($resolves);
})->with([['131750.00', true], ['131749.99', false]]);

test('an action_in_progress issue only gets informational outcomes and is never resolved', function () {
    $x = monitoringScenario();
    // Back to action: a linked task was reopened while monitoring (so a task is open again).
    $open = new Task(['department_id' => $x->department->id, 'issue_id' => $x->decline->id, 'title' => 'Reopened', 'description' => '', 'assignee_id' => $x->owner->id, 'assigned_by' => $x->owner->id, 'due_date' => '2026-10-20']);
    $open->forceFill(['status' => 'in_progress'])->save();
    app(IssueLifecycle::class)->returnToAction($x->decline->fresh(), null);
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');

    $outcomes = Outcome::where('issue_id', $x->decline->id)->get();
    expect($outcomes->pluck('phase')->map->value->unique()->all())->toBe(['during_action'])->and($outcomes->where('resolution_condition_met', true))->toHaveCount(0)
        ->and($x->decline->fresh()->status)->toBe(IssueStatus::ActionInProgress);
});
