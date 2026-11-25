<?php

require_once __DIR__.'/helpers.php';

use App\Enums\IssueStatus;
use App\Models\Outcome;
use App\Models\Task;
use App\Models\User;

test('reopening a task returns the issue to action without touching outcomes; a later monitoring date moves the phase boundary', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    $priya = User::where('email', 'priya@meridian.demo')->first();
    $task = Task::where('issue_id', $x->decline->id)->firstOrFail();
    $before = Outcome::orderBy('id')->get()->map(fn ($o) => $o->only(['id', 'followup_value', 'phase', 'result']) + ['evidence' => json_encode($o->evidence)])->all();
    $actionStarted = $x->decline->fresh()->action_started_at;
    $baseline = $x->decline->baseline_evaluation_id;

    $this->actingAs($x->owner)->post("/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertRedirect(); // reopen (management only)
    $issue = $x->decline->fresh();
    expect($issue->status)->toBe(IssueStatus::ActionInProgress)->and($issue->monitoring_started_at)->toBeNull()->and($issue->action_started_at->equalTo($actionStarted))->toBeTrue()
        ->and($issue->baseline_evaluation_id)->toBe($baseline)
        ->and(Outcome::orderBy('id')->get()->map(fn ($o) => $o->only(['id', 'followup_value', 'phase', 'result']) + ['evidence' => json_encode($o->evidence)])->all())->toEqual($before);

    // Completed again on 6 Oct: monitoring restarts that day, so a week starting 5 Oct is no longer "after monitoring began".
    setClock('2026-10-06 09:00');
    $this->actingAs($priya)->post("/tasks/{$task->id}/status", ['status' => 'completed'])->assertRedirect();
    expect($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring)->and($x->decline->fresh()->monitoring_started_at->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'))->toBe('2026-10-06');

    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');
    $o = latestOutcome($x->decline);
    expect($o->phase->value)->toBe('during_action')->and($o->followup_value)->toBe('150000.00')->and($o->resolution_condition_met)->toBeFalse()
        ->and($x->decline->fresh()->status)->toBe(IssueStatus::Monitoring);
});

test('reopening a task of a resolved issue leaves the issue and every outcome unchanged', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');
    expect($x->decline->fresh()->status)->toBe(IssueStatus::Resolved);
    $outcomes = Outcome::orderBy('id')->get()->map(fn ($o) => $o->only(['id', 'followup_value', 'result']) + ['evidence' => json_encode($o->evidence)])->all();
    $task = Task::where('issue_id', $x->decline->id)->firstOrFail();

    $this->actingAs($x->owner)->post("/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertRedirect();

    expect($x->decline->fresh()->status)->toBe(IssueStatus::Resolved)->and($x->decline->fresh()->resolution_type->value)->toBe('system_verified')
        ->and(Outcome::orderBy('id')->get()->map(fn ($o) => $o->only(['id', 'followup_value', 'result']) + ['evidence' => json_encode($o->evidence)])->all())->toEqual($outcomes);
});
