<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\RuleKey;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\TransitionResult;
use App\Services\Tasks\IssueTaskCompletion;
use App\Support\BusinessClock;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f);
    $this->issue = $this->x->issue;
});

function approveDraft(int $i, array $body = [], $as = null)
{
    $x = test()->x;

    return test()->actingAs($as ?? test()->f->owner)->post("/task-drafts/{$x->drafts[$i]->id}/approve", $body + ['issue_id' => $x->issue->id]);
}

function complete(Task $task, $as)
{
    test()->actingAs($as)->post("/tasks/{$task->id}/status", ['status' => 'in_progress']);

    return test()->actingAs($as)->post("/tasks/{$task->id}/status", ['status' => 'completed']);
}

test('the first approved task on an open issue starts the action; the second changes nothing', function () {
    expect($this->issue->fresh()->status)->toBe(IssueStatus::Open)->and($this->issue->fresh()->action_started_at)->toBeNull();

    approveDraft(0)->assertRedirect();
    $issue = $this->issue->fresh();
    expect($issue->status)->toBe(IssueStatus::ActionInProgress)->and($issue->action_started_at)->not->toBeNull()->and(Task::first()->issue_id)->toBe($issue->id);

    $startedAt = $issue->action_started_at;
    approveDraft(1)->assertRedirect();
    expect($this->issue->fresh()->action_started_at->equalTo($startedAt))->toBeTrue()->and(Task::count())->toBe(2);
});

test('a task without a chosen issue never touches the issue lifecycle', function () {
    $this->actingAs($this->f->owner)->post("/task-drafts/{$this->x->drafts[0]->id}/approve")->assertRedirect();

    expect(Task::first()->issue_id)->toBeNull()->and($this->issue->fresh()->status)->toBe(IssueStatus::Open);
});

test('completing every linked task moves the issue to monitoring and never to resolved', function () {
    approveDraft(0);
    approveDraft(1);
    [$priya, $farid] = Task::orderBy('id')->get()->all();

    complete($priya, $this->f->priya);
    expect($this->issue->fresh()->status)->toBe(IssueStatus::ActionInProgress)->and(app(IssueTaskCompletion::class)->isSatisfied($this->issue->id))->toBeFalse();

    complete($farid, $this->f->farid);
    $issue = $this->issue->fresh();
    expect($issue->status)->toBe(IssueStatus::Monitoring)->and($issue->monitoring_started_at)->not->toBeNull()->and($issue->resolved_at)->toBeNull()
        ->and($issue->status)->not->toBe(IssueStatus::Resolved)->and(app(IssueTaskCompletion::class)->isSatisfied($issue->id))->toBeTrue();
});

test('cancelled tasks are ignored; all cancelled leaves the issue unchanged', function () {
    approveDraft(0);
    approveDraft(1);
    [$priya, $farid] = Task::orderBy('id')->get()->all();

    $this->actingAs($this->f->owner)->post("/tasks/{$farid->id}/status", ['status' => 'cancelled']);
    expect($this->issue->fresh()->status)->toBe(IssueStatus::ActionInProgress); // priya's task is still open

    complete($priya, $this->f->priya);
    expect($this->issue->fresh()->status)->toBe(IssueStatus::Monitoring); // one completed, one cancelled

    // Separate issue: every task cancelled means the condition is false.
    $other = fakeIssue($this->f->sales, IssueStatus::ActionInProgress, IssueSeverity::Low, RuleKey::SalesTargetShortfall);
    $task = makeTask($this->f, $this->f->priya, ['issue_id' => $other->id]);
    $this->actingAs($this->f->owner)->post("/tasks/{$task->id}/status", ['status' => 'cancelled']);
    expect($other->fresh()->status)->toBe(IssueStatus::ActionInProgress)->and(app(IssueTaskCompletion::class)->isSatisfied($other->id))->toBeFalse();
});

test('the last open task being cancelled re-checks the condition', function () {
    approveDraft(0);
    approveDraft(1);
    [$priya, $farid] = Task::orderBy('id')->get()->all();

    complete($priya, $this->f->priya);
    expect($this->issue->fresh()->status)->toBe(IssueStatus::ActionInProgress);
    $this->actingAs($this->f->owner)->post("/tasks/{$farid->id}/status", ['status' => 'cancelled']);

    expect($this->issue->fresh()->status)->toBe(IssueStatus::Monitoring);
});

test('task completion never resolves a manually monitored or resolved issue and ignores a stale bridge call', function () {
    approveDraft(0);
    $task = Task::first();
    $this->issue->forceFill(['status' => IssueStatus::Resolved, 'resolved_at' => now(), 'resolution_type' => 'manual', 'resolution_note' => 'Fixed by hand'])->save();

    complete($task, $this->f->priya)->assertRedirect(); // no exception, no change
    expect($this->issue->fresh()->status)->toBe(IssueStatus::Resolved)->and($task->fresh()->status->value)->toBe('completed');
});

test('an issue lifecycle failure after commit never undoes the approved task', function () {
    app()->bind(IssueLifecycle::class, fn () => new class(app(BusinessClock::class)) extends IssueLifecycle
    {
        public function startAction(Issue $issue, ?User $actor = null, string $trigger = 'task_approved'): TransitionResult
        {
            throw new RuntimeException('lifecycle down');
        }
    });

    approveDraft(0)->assertRedirect();

    expect(Task::count())->toBe(1)->and($this->x->drafts[0]->fresh()->status->value)->toBe('approved')->and($this->issue->fresh()->status)->toBe(IssueStatus::Open);
});

test('the issue page lists the linked tasks with progress, for management only', function () {
    approveDraft(0);
    $this->actingAs($this->f->manager)->get("/issues/{$this->issue->id}")->assertInertia(fn ($p) => $p
        ->where('tasks.counts.pending', 1)->has('tasks.items', 1)->where('tasks.items.0.title', 'Call the ten biggest lapsed Penang accounts')
        ->missing('tasks.items.0.description'));
    $this->actingAs($this->f->priya)->get("/issues/{$this->issue->id}")->assertForbidden();
});
