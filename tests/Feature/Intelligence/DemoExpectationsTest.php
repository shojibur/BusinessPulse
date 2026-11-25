<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Events\Intelligence\EvaluationRunCompleted;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\User;
use App\Services\Intelligence\IssueLifecycle;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;

// Requirement 18: the engine reproduces the documented demonstration values from real calculations and persisted
// data. Every number below is read from database/demo/expected.json (hand-written literals), never hardcoded in
// application logic.

/**
 * Check the evaluations stored since $afterId against a fixture's expected entries of the given trigger.
 */
function assertFixtureEvaluations(string $fixture, int $afterId, string $trigger = 'import'): void
{
    foreach (expectedFixtures()[$fixture]['evaluations'] as $expected) {
        if ($expected['trigger'] === $trigger) {
            assertMatchesExpected(latestEvaluation($expected['rule'], $afterId), $expected);
        }
    }
}

test('the demonstration timeline produces the documented evaluations, issues and notifications', function () {
    $owner = demoOwner();
    $department = demoDepartment();
    $manager = User::where('email', 'aisyah@meridian.demo')->first();

    // 21 Sep: history is healthy.
    $mark = lastEvaluationId();
    importFixture($owner, 'history', '2026-09-21 09:00');
    assertFixtureEvaluations('history', $mark);
    expect(Issue::count())->toBe(0)->and(DatabaseNotification::count())->toBe(0);

    // 28 Sep, before the week 8 upload: Refresh intelligence sees an absent closed week and 0 of 18 received.
    setClock('2026-09-28 09:00');
    $mark = lastEvaluationId();
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    assertFixtureEvaluations('history', $mark, 'refresh');
    expect(Issue::count())->toBe(1)
        ->and(Issue::first()->title)->toBe('Missing sales data')
        ->and(Issue::first()->severity)->toBe(IssueSeverity::Medium)
        ->and(DatabaseNotification::count())->toBe(0); // medium creates no urgent notification

    // 28 Sep: week 8 arrives.
    $mark = lastEvaluationId();
    $import = importFixture($owner, 'week8-decline');
    assertFixtureEvaluations('week8-decline', $mark);

    $decline = Issue::where('title', 'Weekly revenue decline')->firstOrFail();
    $target = Issue::where('title', 'Sales target shortfall')->firstOrFail();
    expect($decline->status)->toBe(IssueStatus::Open)->and($decline->severity)->toBe(IssueSeverity::High)->and($decline->detection_count)->toBe(1)
        ->and($target->status)->toBe(IssueStatus::Open)->and($target->severity)->toBe(IssueSeverity::Medium)
        ->and(Issue::where('status', '!=', 'resolved')->count())->toBe(3); // missing data issue from the refresh is still open

    // One urgent notification set (Owner and Manager) for the decline issue, none for the target issue.
    $urgent = DatabaseNotification::all();
    expect($urgent)->toHaveCount(2)
        ->and($urgent->pluck('notifiable_id')->sort()->values()->all())->toBe(collect([$owner->id, $manager->id])->sort()->values()->all())
        ->and($urgent->every(fn ($n) => $n->data['issue_id'] === $decline->id && $n->data['severity'] === 'high' && $n->data['dedupe_key'] === "{$decline->id}:high"))->toBeTrue()
        ->and($urgent->first()->data['summary_line'])->toBe('Revenue fell 20.00% (RM 124,000.00 vs RM 155,000.00)')
        ->and($urgent->first()->data['department_name'])->toBe('Sales')
        ->and($urgent->first()->data['url'])->toBe("/issues/{$decline->id}");

    // The evaluation was triggered by the import.
    expect(RuleEvaluation::where('import_id', $import->id)->count())->toBe(3);

    // Re-uploading week 8 unchanged at the same clock creates no evaluation, issue or notification.
    $evaluations = RuleEvaluation::count();
    importFixture($owner, 'week8-decline');
    expect(RuleEvaluation::count())->toBe($evaluations)->and(Issue::count())->toBe(3)->and(DatabaseNotification::count())->toBe(2)
        ->and($decline->fresh()->detection_count)->toBe(1);

    // The decline issue goes through action to monitoring, then weeks 9 and 10 arrive: S04 leaves it untouched
    // (this path skips the optional correction, so week 9 compares with 124,000).
    $lifecycle = app(IssueLifecycle::class);
    $lifecycle->startAction($decline, null);
    $lifecycle->startMonitoring($decline, null);
    $snapshot = $decline->fresh()->only(['status', 'detection_count', 'latest_evaluation_id', 'severity', 'baseline_evaluation_id']);

    $mark = lastEvaluationId();
    importFixture($owner, 'week9-interim', '2026-10-05 09:00');
    assertFixtureEvaluations('week9-interim', $mark);
    expect($decline->fresh()->only(['status', 'detection_count', 'latest_evaluation_id', 'severity', 'baseline_evaluation_id']))->toBe($snapshot)
        ->and($decline->fresh()->status)->toBe(IssueStatus::Monitoring);

    $mark = lastEvaluationId();
    importFixture($owner, 'week10-recovery', '2026-10-12 09:00');
    assertFixtureEvaluations('week10-recovery', $mark);
    expect($decline->fresh()->only(['status', 'detection_count', 'latest_evaluation_id', 'severity', 'baseline_evaluation_id']))->toBe($snapshot)
        ->and($target->fresh()->status)->toBe(IssueStatus::Open); // resolution belongs to S08
});

test('a correction re-detects the decline, clears the target and preserves the historical baseline evidence', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    importFixture($owner, 'week8-decline', '2026-09-28 09:00');

    $decline = Issue::where('title', 'Weekly revenue decline')->firstOrFail();
    $target = Issue::where('title', 'Sales target shortfall')->firstOrFail();

    $baseline = RuleEvaluation::find($decline->baseline_evaluation_id);
    $baselineJson = json_encode($baseline->fresh()->getAttributes());
    $targetBaseline = RuleEvaluation::find($target->baseline_evaluation_id);
    $targetBaselineJson = json_encode($targetBaseline->fresh()->getAttributes());

    $mark = lastEvaluationId();
    importFixture($owner, 'week8-correction');
    assertFixtureEvaluations('week8-correction', $mark);

    $decline->refresh();
    $target->refresh();
    expect($decline->detection_count)->toBe(2)->and($decline->severity)->toBe(IssueSeverity::High)
        ->and($decline->baseline_evaluation_id)->toBe($baseline->id)
        ->and($decline->latest_evaluation_id)->not->toBe($baseline->id)
        ->and(json_encode(RuleEvaluation::find($baseline->id)->getAttributes()))->toBe($baselineJson)
        ->and(json_encode(RuleEvaluation::find($targetBaseline->id)->getAttributes()))->toBe($targetBaselineJson)
        ->and($target->detection_count)->toBe(1)->and($target->latest_evaluation_id)->toBe($target->baseline_evaluation_id)
        ->and(DatabaseNotification::count())->toBe(2);

});

test('an upload with errors leaves the week incomplete: coverage breaches, money rules are insufficient', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');

    $mark = lastEvaluationId();
    importFixture($owner, 'week8-with-errors', '2026-09-28 09:00');
    assertFixtureEvaluations('week8-with-errors', $mark);

    // Only the missing data issue exists: no misleading financial finding came from incomplete data.
    expect(Issue::pluck('title')->all())->toBe(['Missing sales data'])
        ->and(Issue::first()->severity)->toBe(IssueSeverity::Medium)
        ->and(DatabaseNotification::count())->toBe(0);

    $gap = latestEvaluation('missing_sales_data')->evidence['coverage']['missingDueBranchDays'];
    expect($gap)->toEqual([['branch_code' => 'JB', 'date' => '2026-09-26', 'kind' => 'inside_period']]);
});

test('EvaluationRunCompleted is dispatched once per run with sorted unique ids, also when everything is reused', function () {
    $owner = demoOwner();
    $department = demoDepartment();
    importFixture($owner, 'history', '2026-09-21 09:00');
    Event::fake([EvaluationRunCompleted::class]);

    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id);
    app(DispatchEvaluation::class)->handle($department->id, null, $owner->id); // identical inputs: all reused

    Event::assertDispatchedTimes(EvaluationRunCompleted::class, 2);
    $ids = RuleEvaluation::orderBy('id')->pluck('id')->all();

    Event::assertDispatched(EvaluationRunCompleted::class, fn (EvaluationRunCompleted $event) => $event->departmentId === $department->id
        && $event->importId === null
        && $event->actorUserId === $owner->id
        && $event->evaluationIds === $ids
        && $event->evaluationIds === array_values(array_unique($event->evaluationIds)));

    expect(RuleEvaluation::count())->toBe(3);
});
