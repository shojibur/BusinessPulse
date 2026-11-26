<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Events\Intelligence\IssueCreated;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Services\Intelligence\IssueLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

function breachWeek8(): Issue
{
    setClock('2026-09-28 09:00');
    importCsv(test()->owner, weekCsv('2026-09-14', '1000.00', '1000.00'));
    importCsv(test()->owner, weekCsv('2026-09-21', '700.00', '1000.00')); // -30% and 70% of target

    return Issue::where('title', 'Weekly revenue decline')->firstOrFail();
}

test('the first breach creates one open issue with a root history entry and the baseline evaluation', function () {
    Event::fake([IssueCreated::class]);
    $issue = breachWeek8();
    $evaluation = RuleEvaluation::find($issue->baseline_evaluation_id);

    expect($issue->status)->toBe(IssueStatus::Open)->and($issue->severity)->toBe(IssueSeverity::High)->and($issue->detection_count)->toBe(1)
        ->and($issue->baseline_evaluation_id)->toBe($issue->latest_evaluation_id)
        ->and($issue->scope_key)->toBe('department')->and($issue->recurrence_of_issue_id)->toBeNull()
        ->and($issue->period_start->format('Y-m-d'))->toBe('2026-09-21')->and($issue->period_end->format('Y-m-d'))->toBe('2026-09-27')
        ->and($issue->first_detected_at->equalTo($evaluation->evaluated_at))->toBeTrue()
        ->and($issue->status_history)->toHaveCount(1)
        ->and($issue->status_history[0])->toMatchArray(['from' => null, 'to' => 'open', 'trigger' => 'created', 'by_user_id' => null])
        ->and($issue->status_history[0]['at'])->toStartWith('2026-09-28T09:00:00+08:00'); // business clock, company timezone

    Event::assertDispatched(IssueCreated::class, fn (IssueCreated $e) => $e->issueId === $issue->id && $e->severity === 'high' && $e->recurrenceOfIssueId === null);
});

test('a second breaching evaluation re-detects the same issue and never changes its status or baseline', function () {
    $issue = breachWeek8();
    $baseline = $issue->baseline_evaluation_id;
    app(IssueLifecycle::class)->acknowledge($issue, $this->owner);

    // A later due day produces a new evaluation (the last due date is part of the run key); the data is unchanged.
    setClock('2026-09-29 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    $issue->refresh();
    expect(Issue::where('title', 'Weekly revenue decline')->count())->toBe(1)
        ->and($issue->detection_count)->toBe(2)->and($issue->status)->toBe(IssueStatus::Acknowledged)
        ->and($issue->baseline_evaluation_id)->toBe($baseline)->and($issue->latest_evaluation_id)->not->toBe($baseline)
        ->and($issue->period_start->format('Y-m-d'))->toBe('2026-09-21')
        ->and(collect($issue->status_history)->last()['trigger'])->toBe('re_detection');
});

test('severity only rises on re-detection and never falls', function () {
    $issue = breachWeek8();
    expect($issue->severity)->toBe(IssueSeverity::High);

    // The threshold is relaxed so the same data would now be medium; the issue keeps high.
    BusinessRule::where('key', 'weekly_revenue_decline')->update(['threshold' => '15.00', 'params' => ['high_drop_pct' => '90.00']]);
    setClock('2026-09-29 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    expect(latestEvaluation('weekly_revenue_decline')->severity)->toBe(IssueSeverity::Medium)
        ->and($issue->fresh()->severity)->toBe(IssueSeverity::High)
        ->and($issue->fresh()->detection_count)->toBe(2);
});

test('the database allows only one open issue per rule and department', function () {
    $issue = breachWeek8();

    $duplicate = fn () => DB::transaction(fn () => Issue::create([
        'department_id' => $issue->department_id, 'business_rule_id' => $issue->business_rule_id, 'scope_key' => 'department',
        'title' => 'Weekly revenue decline', 'severity' => 'high', 'status' => 'acknowledged',
        'first_detected_at' => now(), 'last_detected_at' => now(), 'period_start' => '2026-09-21', 'period_end' => '2026-09-27',
        'baseline_evaluation_id' => $issue->baseline_evaluation_id, 'latest_evaluation_id' => $issue->baseline_evaluation_id,
    ]));

    expect($duplicate)->toThrow(QueryException::class);

    // A resolved issue of the same identity does not block a new open one.
    app(IssueLifecycle::class)->resolveManual($issue, $this->owner, 'Handled.');
    expect(fakeIssue($this->f->sales)->status)->toBe(IssueStatus::Open);
});

test('after resolution a new breach creates a linked recurrence issue with a fresh baseline', function () {
    $first = breachWeek8();
    app(IssueLifecycle::class)->resolveManual($first, $this->owner, 'Fixed at the source.');
    $resolvedSnapshot = $first->fresh()->only(['status', 'detection_count', 'resolution_note', 'latest_evaluation_id']);
    Event::fake([IssueCreated::class]);

    setClock('2026-09-29 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    $second = Issue::where('title', 'Weekly revenue decline')->where('id', '!=', $first->id)->firstOrFail();
    expect($second->recurrence_of_issue_id)->toBe($first->id)->and($second->status)->toBe(IssueStatus::Open)
        ->and($second->detection_count)->toBe(1)->and($second->baseline_evaluation_id)->not->toBe($first->baseline_evaluation_id)
        ->and($first->fresh()->only(['status', 'detection_count', 'resolution_note', 'latest_evaluation_id']))->toBe($resolvedSnapshot);

    Event::assertDispatched(IssueCreated::class, fn (IssueCreated $e) => $e->recurrenceOfIssueId === $first->id);
});

test('not_breached and insufficient_data evaluations leave an open issue untouched', function () {
    $issue = breachWeek8();
    $snap = fn () => [...$issue->fresh()->only(['status', 'severity', 'detection_count', 'latest_evaluation_id']), 'last' => $issue->fresh()->last_detected_at->toIso8601String()];
    $snapshot = $snap();

    // Week 8 is corrected upward: the next evaluation is not breached.
    importCsv($this->owner, weekCsv('2026-09-21', '1100.00', '1000.00'));
    expect(latestEvaluation('weekly_revenue_decline')->result->value)->toBe('not_breached');

    // A much later clock makes week 8 old but still the latest closed week until week 9 closes... then data is absent.
    setClock('2026-10-06 12:00');
    app(DispatchEvaluation::class)->handle($this->f->sales->id);
    expect(latestEvaluation('weekly_revenue_decline')->result->value)->toBe('insufficient_data');

    expect($snap())->toBe($snapshot);
});
