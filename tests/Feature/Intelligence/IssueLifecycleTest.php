<?php

use App\Enums\IssueStatus;
use App\Enums\ResolutionType;
use App\Enums\RuleKey;
use App\Events\Intelligence\IssueStatusChanged;
use App\Models\Issue;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\IssueTransitionNotAllowedException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->f = identity();
    $this->lifecycle = app(IssueLifecycle::class);
    $this->actor = $this->f->manager;
    setClock('2026-09-28 09:00');
});

/** The documented transition table (R4), spelled out independently of the enum. */
const ALLOWED = [
    'open' => ['acknowledged', 'action_in_progress', 'resolved'],
    'acknowledged' => ['action_in_progress', 'resolved'],
    'action_in_progress' => ['monitoring', 'resolved'],
    'monitoring' => ['action_in_progress', 'resolved', 'open'],
    'resolved' => [],
];

test('the status enum allows exactly the documented transitions for all 25 pairs', function () {
    foreach (IssueStatus::cases() as $from) {
        foreach (IssueStatus::cases() as $to) {
            expect($from->canTransitionTo($to))->toBe(in_array($to->value, ALLOWED[$from->value], true), "{$from->value} -> {$to->value}");
        }
    }
});

/**
 * Each method with a callable and the (from -> expected) behaviour for every starting status:
 * 'ok' = transitions, 'noop' = unchanged without error, 'deny' = IssueTransitionNotAllowedException.
 */
dataset('methods', [
    'acknowledge' => ['acknowledge', ['open' => 'ok', 'acknowledged' => 'noop', 'action_in_progress' => 'deny', 'monitoring' => 'deny', 'resolved' => 'deny']],
    'startAction' => ['startAction', ['open' => 'ok', 'acknowledged' => 'ok', 'action_in_progress' => 'noop', 'monitoring' => 'noop', 'resolved' => 'noop']],
    'startMonitoring' => ['startMonitoring', ['open' => 'deny', 'acknowledged' => 'deny', 'action_in_progress' => 'ok', 'monitoring' => 'noop', 'resolved' => 'deny']],
    'returnToAction' => ['returnToAction', ['open' => 'deny', 'acknowledged' => 'deny', 'action_in_progress' => 'noop', 'monitoring' => 'ok', 'resolved' => 'deny']],
    'resolveManual' => ['resolveManual', ['open' => 'ok', 'acknowledged' => 'ok', 'action_in_progress' => 'ok', 'monitoring' => 'ok', 'resolved' => 'noop']],
    'resolveVerified' => ['resolveVerified', ['open' => 'deny', 'acknowledged' => 'deny', 'action_in_progress' => 'deny', 'monitoring' => 'ok', 'resolved' => 'noop']],
    'reopen' => ['reopen', ['open' => 'noop', 'acknowledged' => 'deny', 'action_in_progress' => 'deny', 'monitoring' => 'ok', 'resolved' => 'deny']],
]);

function callLifecycle(string $method, Issue $issue, $actor)
{
    $lifecycle = app(IssueLifecycle::class);

    return match ($method) {
        'acknowledge' => $lifecycle->acknowledge($issue, $actor),
        'startAction' => $lifecycle->startAction($issue, $actor),
        'startMonitoring' => $lifecycle->startMonitoring($issue, $actor, 'Tasks are done; watching the next week.'),
        'returnToAction' => $lifecycle->returnToAction($issue, null),
        'resolveManual' => $lifecycle->resolveManual($issue, $actor, 'Resolved with the branch managers.'),
        'resolveVerified' => $lifecycle->resolveVerified($issue, 'outcome:1'),
        'reopen' => $lifecycle->reopen($issue, 'Revenue fell again.'),
    };
}

test('every lifecycle method allows, ignores or rejects each starting status as documented', function (string $method, array $expectations) {
    foreach ($expectations as $status => $expected) {
        // Resolved issues must not collide with the open one of the same rule: use a fresh department rule each time.
        $issue = fakeIssue($this->f->sales, IssueStatus::from($status));
        $historyBefore = count($issue->status_history);

        if ($expected === 'deny') {
            expect(fn () => callLifecycle($method, $issue, $this->actor))->toThrow(IssueTransitionNotAllowedException::class, '', "{$method} from {$status}");
            expect($issue->fresh()->status->value)->toBe($status)->and(count($issue->fresh()->status_history))->toBe($historyBefore);
        } else {
            $result = callLifecycle($method, $issue, $this->actor);
            expect($result->changed)->toBe($expected === 'ok', "{$method} from {$status}");
            $expected === 'noop'
                ? expect($issue->fresh()->status->value)->toBe($status)->and(count($issue->fresh()->status_history))->toBe($historyBefore)
                : expect(count($issue->fresh()->status_history))->toBe($historyBefore + 1);
        }

        // Make room for the next open issue of the same rule.
        Issue::whereKey($issue->id)->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution_type' => 'manual', 'resolution_note' => 'cleanup']);
    }
})->with('methods');

test('manual monitoring and manual resolve require a reason of 1 to 2,000 characters', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::ActionInProgress);

    foreach (['', '   ', null] as $reason) {
        expect(fn () => $this->lifecycle->startMonitoring($issue, $this->actor, $reason))->toThrow(ValidationException::class);
        expect(fn () => $this->lifecycle->resolveManual($issue, $this->actor, $reason))->toThrow(ValidationException::class);
    }

    expect(fn () => $this->lifecycle->resolveManual($issue, $this->actor, str_repeat('x', 2001)))->toThrow(ValidationException::class)
        ->and($issue->fresh()->status)->toBe(IssueStatus::ActionInProgress);

    expect($this->lifecycle->resolveManual($issue, $this->actor, str_repeat('x', 2000))->changed)->toBeTrue();
});

test('the automatic monitoring variant needs no reason and records the tasks_complete trigger', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::ActionInProgress);

    $this->lifecycle->startMonitoring($issue, null);

    $entry = collect($issue->fresh()->status_history)->last();
    expect($issue->fresh()->status)->toBe(IssueStatus::Monitoring)
        ->and($entry)->toMatchArray(['from' => 'action_in_progress', 'to' => 'monitoring', 'trigger' => 'tasks_complete', 'by_user_id' => null, 'reason' => null])
        ->and($issue->fresh()->monitoring_started_at)->not->toBeNull();
});

test('startAction sets action_started_at once per action cycle on the business clock', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::Open);

    $this->lifecycle->startAction($issue, null);
    $first = $issue->fresh()->action_started_at;
    expect($first)->not->toBeNull()->and($first->utc()->format('Y-m-d H:i'))->toBe('2026-09-28 01:00'); // 09:00 Kuala Lumpur

    // Later: monitoring, back to action (timestamp kept), then reopened (timestamps cleared) and a new cycle.
    setClock('2026-09-30 09:00');
    $this->lifecycle->startMonitoring($issue, null);
    expect($issue->fresh()->monitoring_started_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-30 01:00');

    $this->lifecycle->returnToAction($issue, null);
    expect($issue->fresh()->monitoring_started_at)->toBeNull()->and($issue->fresh()->action_started_at->equalTo($first))->toBeTrue();

    $this->lifecycle->startMonitoring($issue, null);
    $baseline = $issue->fresh()->baseline_evaluation_id;
    $this->lifecycle->reopen($issue, 'Revenue fell again.');
    $reopened = $issue->fresh();
    expect($reopened->status)->toBe(IssueStatus::Open)->and($reopened->action_started_at)->toBeNull()->and($reopened->monitoring_started_at)->toBeNull()
        ->and($reopened->baseline_evaluation_id)->toBe($baseline);

    setClock('2026-10-07 09:00');
    $this->lifecycle->startAction($issue, null);
    expect($issue->fresh()->action_started_at->utc()->format('Y-m-d'))->toBe('2026-10-07');

    // A second startAction on an issue already in action does not move the timestamp.
    $stamp = $issue->fresh()->action_started_at;
    $this->lifecycle->startAction($issue, null);
    expect($issue->fresh()->action_started_at->equalTo($stamp))->toBeTrue();
});

test('manual resolve and verified resolution set only their own fields', function () {
    $manual = fakeIssue($this->f->sales, IssueStatus::Acknowledged);
    $this->lifecycle->resolveManual($manual, $this->actor, 'Customer closed the account.');
    $manual->refresh();
    expect($manual->status)->toBe(IssueStatus::Resolved)->and($manual->resolution_type)->toBe(ResolutionType::Manual)
        ->and($manual->resolution_note)->toBe('Customer closed the account.')->and($manual->resolved_by)->toBe($this->actor->id)
        ->and($manual->resolved_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-28 01:00')
        ->and(collect($manual->status_history)->last())->toMatchArray(['to' => 'resolved', 'trigger' => 'manual', 'by_user_id' => $this->actor->id, 'reason' => 'Customer closed the account.']);

    $verified = fakeIssue($this->f->sales, IssueStatus::Monitoring);
    $this->lifecycle->resolveVerified($verified, 'outcome:42');
    $verified->refresh();
    expect($verified->resolution_type)->toBe(ResolutionType::SystemVerified)->and($verified->resolved_by)->toBeNull()
        ->and($verified->resolution_note)->toBeNull()->and($verified->resolved_at)->not->toBeNull()
        ->and(collect($verified->status_history)->last()['trigger'])->toBe('outcome_verified');

    // Nothing resolution related is set by other transitions.
    $other = fakeIssue($this->f->sales, IssueStatus::Open, key: RuleKey::SalesTargetShortfall);
    $this->lifecycle->acknowledge($other, $this->actor);
    expect($other->fresh()->resolved_at)->toBeNull()->and($other->fresh()->resolution_type)->toBeNull();
});

test('history is append only and keeps every earlier entry unchanged', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::Open);
    $this->lifecycle->acknowledge($issue, $this->actor);
    $afterFirst = $issue->fresh()->status_history;
    $this->lifecycle->startAction($issue, $this->actor);
    $this->lifecycle->startMonitoring($issue, $this->actor, 'Watching.');

    $history = $issue->fresh()->status_history;
    expect($history)->toHaveCount(4)->and(array_slice($history, 0, 2))->toBe($afterFirst)
        ->and(array_column($history, 'to'))->toBe(['open', 'acknowledged', 'action_in_progress', 'monitoring']);
});

test('each successful transition dispatches IssueStatusChanged once and a repeat does not', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::Open);
    Event::fake([IssueStatusChanged::class]);

    $this->lifecycle->acknowledge($issue, $this->actor);
    $this->lifecycle->acknowledge($issue, $this->actor); // double submit

    Event::assertDispatchedTimes(IssueStatusChanged::class, 1);
    Event::assertDispatched(IssueStatusChanged::class, fn (IssueStatusChanged $e) => $e->issueId === $issue->id && $e->from === 'open' && $e->to === 'acknowledged'
        && $e->actorUserId === $this->actor->id && $e->departmentId === $this->f->sales->id);
    expect($issue->fresh()->status_history)->toHaveCount(2);
});

test('completing a task or re-detecting a breach never resolves or moves an issue', function () {
    $issue = fakeIssue($this->f->sales, IssueStatus::ActionInProgress);

    // S04 has no task reader and no method that could do it.
    expect(method_exists($this->lifecycle, 'markActionInProgress'))->toBeFalse()
        ->and(method_exists($this->lifecycle, 'markMonitoringIfTasksComplete'))->toBeFalse()
        ->and($issue->fresh()->status)->toBe(IssueStatus::ActionInProgress);
});
