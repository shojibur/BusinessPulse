<?php

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\Tasks\OverdueCondition;
use App\Services\Tasks\TaskCounts;
use App\Services\Tasks\TaskStatusMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->f = meetingRoster();
    setClock('2026-09-28 09:00');
    $this->task = makeTask($this->f, $this->f->priya);
});

function setStatus(Task $task, string $status, $as)
{
    return test()->actingAs($as)->post("/tasks/{$task->id}/status", ['status' => $status]);
}

test('the status machine allows exactly the documented transitions', function () {
    $table = ['pending' => ['in_progress', 'blocked', 'completed', 'cancelled'], 'in_progress' => ['blocked', 'completed', 'cancelled'], 'blocked' => ['in_progress', 'cancelled'], 'completed' => ['in_progress'], 'cancelled' => []];

    foreach (TaskStatus::cases() as $from) {
        foreach (TaskStatus::cases() as $to) {
            expect(TaskStatusMachine::allows($from, $to))->toBe(in_array($to->value, $table[$from->value], true), "{$from->value} -> {$to->value}");
        }
    }
});

test('the assigned employee moves a task forward and completion records the business time', function () {
    Event::fake([TaskStatusChanged::class, TaskCompleted::class]);

    setStatus($this->task, 'in_progress', $this->f->priya)->assertRedirect();
    setStatus($this->task, 'blocked', $this->f->priya)->assertRedirect();
    setStatus($this->task, 'in_progress', $this->f->priya)->assertRedirect();
    setStatus($this->task, 'completed', $this->f->priya)->assertRedirect();

    $task = $this->task->fresh();
    expect($task->status)->toBe(TaskStatus::Completed)->and($task->completed_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-28 01:00'); // 09:00 Kuala Lumpur
    Event::assertDispatchedTimes(TaskStatusChanged::class, 4);
    Event::assertDispatchedTimes(TaskCompleted::class, 1);
});

test('invalid transitions are 422 with the current status, same status is a no-op, pending is never a target', function () {
    Event::fake([TaskStatusChanged::class]);
    setStatus($this->task, 'pending', $this->f->priya)->assertRedirect(); // same status: no-op
    setStatus($this->task, 'completed', $this->f->priya);
    setStatus($this->task, 'blocked', $this->f->priya)->assertSessionHasErrors('status');
    setStatus($this->task, 'pending', $this->f->owner)->assertSessionHasErrors('status');
    setStatus($this->task, 'bogus', $this->f->priya)->assertSessionHasErrors('status');

    expect($this->task->fresh()->status)->toBe(TaskStatus::Completed);
    Event::assertDispatchedTimes(TaskStatusChanged::class, 1);
});

test('staff cannot cancel or reopen; owner and the department manager can', function () {
    setStatus($this->task, 'cancelled', $this->f->priya)->assertForbidden();
    setStatus($this->task, 'completed', $this->f->priya);
    setStatus($this->task, 'in_progress', $this->f->priya)->assertForbidden(); // reopen
    expect($this->task->fresh()->status)->toBe(TaskStatus::Completed);

    setStatus($this->task, 'in_progress', $this->f->manager)->assertRedirect();
    expect($this->task->fresh()->status)->toBe(TaskStatus::InProgress)->and($this->task->fresh()->completed_at)->toBeNull();
    setStatus($this->task, 'cancelled', $this->f->owner)->assertRedirect();
    expect($this->task->fresh()->status)->toBe(TaskStatus::Cancelled);
    setStatus($this->task, 'in_progress', $this->f->owner)->assertSessionHasErrors('status'); // cancelled is final
});

test('the database rejects a completed task without completed_at and a completed_at on another status', function () {
    $row = ['department_id' => $this->f->sales->id, 'title' => 'x', 'assignee_id' => $this->f->priya->id, 'assigned_by' => $this->f->manager->id, 'due_date' => '2026-10-02', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => DB::table('tasks')->insert($row + ['status' => 'completed', 'completed_at' => null]))->toThrow(QueryException::class);
    expect(fn () => DB::table('tasks')->insert($row + ['status' => 'pending', 'completed_at' => now()]))->toThrow(QueryException::class);
    expect(fn () => DB::table('tasks')->insert($row + ['status' => 'overdue', 'completed_at' => null]))->toThrow(QueryException::class);
});

test('comments are plain text, validated, in every status and do not change the task', function () {
    $before = $this->task->updated_at;
    $this->actingAs($this->f->priya)->post("/tasks/{$this->task->id}/comments", ['body' => "  <b>Called two accounts</b>\nthree to go  "])->assertRedirect();
    setStatus($this->task, 'cancelled', $this->f->owner);
    $this->actingAs($this->f->manager)->post("/tasks/{$this->task->id}/comments", ['body' => 'Noted'])->assertRedirect();

    $this->actingAs($this->f->priya)->post("/tasks/{$this->task->id}/comments", ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($this->f->priya)->post("/tasks/{$this->task->id}/comments", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
    $this->actingAs($this->f->priya)->post("/tasks/{$this->task->id}/comments", ['body' => 'x', 'user_id' => $this->f->owner->id, 'task_id' => 999])->assertRedirect();

    $comments = TaskComment::orderBy('id')->get();
    expect($comments)->toHaveCount(3)->and($comments[0]->body)->toBe("<b>Called two accounts</b>\nthree to go")->and($comments[0]->user_id)->toBe($this->f->priya->id)
        ->and($comments[2]->user_id)->toBe($this->f->priya->id)->and($comments[2]->task_id)->toBe($this->task->id);
    expect($this->actingAs($this->f->manager)->get("/tasks/{$this->task->id}")->viewData('page')['props']['comments'])->toHaveCount(3);
});

test('overdue is computed from the due date and the business date, never stored', function () {
    $dueToday = makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-28']);
    $yesterday = makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-27']);
    $doneLate = makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-20', 'status' => 'completed']);
    $cancelledLate = makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-20', 'status' => 'cancelled']);

    expect(OverdueCondition::isOverdue($dueToday))->toBeFalse()->and(OverdueCondition::isOverdue($yesterday))->toBeTrue()
        ->and(OverdueCondition::daysOverdue($yesterday))->toBe(1)->and(OverdueCondition::daysOverdue($dueToday))->toBeNull()
        ->and(OverdueCondition::isOverdue($doneLate))->toBeFalse()->and(OverdueCondition::isOverdue($cancelledLate))->toBeFalse();
    expect(Schema::hasColumn('tasks', 'is_overdue'))->toBeFalse();

    // The next business day: the same task is now overdue with no write.
    setClock('2026-09-29 09:00');
    expect(OverdueCondition::isOverdue($dueToday->fresh()))->toBeTrue()->and(OverdueCondition::daysOverdue($dueToday->fresh()))->toBe(1);
    setStatus($dueToday, 'completed', $this->f->priya);
    expect(OverdueCondition::isOverdue($dueToday->fresh()))->toBeFalse();
});

test('task counts use one aggregate query and match the list, my tasks and the dashboard', function () {
    makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-20']);                       // overdue
    makeTask($this->f, $this->f->farid, ['due_date' => '2026-10-10', 'status' => 'blocked']);
    makeTask($this->f, $this->f->farid, ['status' => 'completed']);
    makeTask($this->f, $this->f->farid, ['status' => 'cancelled', 'due_date' => '2026-09-01']);
    $counts = app(TaskCounts::class);

    DB::enableQueryLog();
    $company = $counts->forCompany($this->f->company->id);
    expect(collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "tasks"')))->toHaveCount(1);
    DB::flushQueryLog();

    expect($company)->toBe(['open' => 3, 'overdue' => 1])->and($counts->forDepartment($this->f->sales->id))->toBe($company)
        ->and($counts->forUser($this->f->priya->id))->toBe(['open' => 2, 'overdue' => 1])->and($counts->forUser($this->f->owner->id))->toBe(['open' => 0, 'overdue' => 0]);

    $this->actingAs($this->f->priya)->get('/my-tasks')->assertInertia(fn ($p) => $p->where('summary.open', 2)->where('summary.overdue', 1));
    $this->actingAs($this->f->manager)->get('/tasks')->assertInertia(fn ($p) => $p->where('summary.open', 3)->where('summary.overdue', 1));
});

test('the executive dashboard shows persisted task counts, scoped by role', function () {
    makeTask($this->f, $this->f->priya, ['due_date' => '2026-09-20']);
    makeTask($this->f, $this->f->farid);

    foreach ([$this->f->owner, $this->f->manager] as $user) {
        $this->actingAs($user)->get('/today')->assertInertia(fn ($p) => $p->where('data.taskCounts.open', 3)->where('data.taskCounts.overdue', 1));
    }
});
