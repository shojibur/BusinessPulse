<?php

use App\Actions\Tasks\ApproveTaskDraft;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskDraftApproved;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f);
});

test('approving twice returns the same task with one row, one notification and one event', function () {
    Event::fake([TaskDraftApproved::class, TaskAssigned::class]);
    $draft = $this->x->drafts[0];

    $first = $this->actingAs($this->f->owner)->post("/task-drafts/{$draft->id}/approve");
    $second = $this->actingAs($this->f->owner)->post("/task-drafts/{$draft->id}/approve", ['title' => 'Ignored late edit', 'assignee_id' => $this->f->farid->id]);

    $first->assertRedirect();
    $second->assertRedirect();
    expect(Task::count())->toBe(1)->and(Task::first()->assignee_id)->toBe($this->f->priya->id)->and(Task::first()->title)->toBe('Call the ten biggest lapsed Penang accounts');
    expect(DatabaseNotification::where('data->dedupe_key', 'task_assigned:'.Task::first()->id.':'.$this->f->priya->id)->count())->toBe(1);
    Event::assertDispatchedTimes(TaskDraftApproved::class, 1);
    Event::assertDispatchedTimes(TaskAssigned::class, 1);
});

test('a competing task for the same draft hits the unique constraint and the existing task is returned', function () {
    $draft = $this->x->drafts[0];
    // Simulate a competing request that committed first without touching the draft row.
    $winner = new Task(['department_id' => $this->f->sales->id, 'task_draft_id' => $draft->id, 'meeting_id' => $this->x->meeting->id, 'title' => 'Winner', 'assignee_id' => $this->f->priya->id, 'assigned_by' => $this->f->owner->id, 'due_date' => '2026-10-02']);
    $winner->forceFill(['status' => 'pending'])->save();

    $result = app(ApproveTaskDraft::class)->handle($draft, $this->f->owner);

    expect($result->created)->toBeFalse()->and($result->task->id)->toBe($winner->id)->and(Task::count())->toBe(1)
        ->and(DatabaseNotification::count())->toBe(0); // the failed attempt rolled its notification back

    // The database constraint is the final safeguard.
    expect(fn () => DB::table('tasks')->insert(['department_id' => $this->f->sales->id, 'task_draft_id' => $draft->id, 'title' => 'Dup', 'assignee_id' => $this->f->priya->id,
        'assigned_by' => $this->f->owner->id, 'due_date' => '2026-10-02', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]))->toThrow(UniqueConstraintViolationException::class);
});

test('a failure after the task insert rolls back everything: no task, no notification, no event', function () {
    Event::fake([TaskDraftApproved::class, TaskAssigned::class]);
    $draft = $this->x->drafts[0];
    // Make the notification insert fail inside the transaction.
    app()->bind(TaskAssignmentNotifier::class, fn () => new class extends TaskAssignmentNotifier
    {
        public function send(Task $task, User $actor): void
        {
            throw new RuntimeException('notification store down');
        }
    });

    expect(fn () => app(ApproveTaskDraft::class)->handle($draft, $this->f->owner))->toThrow(RuntimeException::class);

    expect(Task::count())->toBe(0)->and($draft->fresh()->status->value)->toBe('pending')->and($draft->fresh()->task_id)->toBeNull();
    Event::assertNotDispatched(TaskDraftApproved::class);
    Event::assertNotDispatched(TaskAssigned::class);
});
