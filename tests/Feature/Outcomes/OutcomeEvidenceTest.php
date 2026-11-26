<?php

require_once __DIR__.'/helpers.php';

use App\Enums\IssueStatus;
use App\Models\Outcome;
use App\Models\Task;

test('a corrected import after the fact never changes the baseline, earlier outcomes or their evidence', function () {
    $x = monitoringScenario();
    $baselineId = $x->decline->baseline_evaluation_id;
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    $before = Outcome::orderBy('id')->get()->map(fn ($o) => $o->only(['id', 'baseline_value', 'followup_value', 'absolute_change', 'relative_change_pct', 'point_change', 'result', 'phase']) + ['evidence' => json_encode($o->evidence)])->all();

    importFixture($x->owner, 'week8-correction', '2026-10-06 09:00'); // corrects W8 revenue to 126,000

    $after = Outcome::orderBy('id')->take(count($before))->get()->map(fn ($o) => $o->only(['id', 'baseline_value', 'followup_value', 'absolute_change', 'relative_change_pct', 'point_change', 'result', 'phase']) + ['evidence' => json_encode($o->evidence)])->all();
    expect($after)->toEqual($before)->and($x->decline->fresh()->baseline_evaluation_id)->toBe($baselineId)
        ->and(latestOutcome($x->decline)->baseline_value)->toBe('124000.00'); // the stored baseline, not the corrected 126,000
});

test('an outcome is immutable except for its note, and can never be deleted', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    $o = latestOutcome($x->decline);

    expect(fn () => $o->update(['followup_value' => '1.00']))->toThrow(LogicException::class);
    expect(fn () => $o->update(['evidence' => ['x' => 1]]))->toThrow(LogicException::class);
    expect(fn () => $o->update(['result' => 'worsened']))->toThrow(LogicException::class);
    expect(fn () => $o->delete())->toThrow(LogicException::class);
    $o = $o->fresh();
    $o->update(['manual_note' => 'Reviewed with the team.']);
    expect($o->fresh()->manual_note)->toBe('Reviewed with the team.');
});

test('verified resolution is refused while a linked task is open, and the issue returns to action', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    // A new task was approved after monitoring began but the lifecycle update has not run yet (the lost-update window).
    $open = new Task(['department_id' => $x->department->id, 'issue_id' => $x->decline->id, 'title' => 'Late task', 'description' => '', 'assignee_id' => $x->owner->id, 'assigned_by' => $x->owner->id, 'due_date' => '2026-10-20']);
    $open->forceFill(['status' => 'pending'])->save();

    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');

    $issue = $x->decline->fresh();
    expect($issue->status)->not->toBe(IssueStatus::Resolved)->and($issue->resolved_at)->toBeNull();
});
