<?php

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Task;
use App\Services\Tasks\TaskStatusMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
