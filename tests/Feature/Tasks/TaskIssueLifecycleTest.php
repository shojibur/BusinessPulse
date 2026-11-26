<?php

use App\Enums\IssueStatus;
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
