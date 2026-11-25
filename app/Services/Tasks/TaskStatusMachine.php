<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\BusinessClock;

/**
 * The only writer of tasks.status. Every transition is one compare-and-set UPDATE, so two racing requests cannot both
 * win, and `completed_at` is written in the same statement (the CHECK constraint ties it to the completed status).
 */
final class TaskStatusMachine
{
    public const INITIAL = TaskStatus::Pending;

    private const ALLOWED = [
        'pending' => ['in_progress', 'blocked', 'completed', 'cancelled'],
        'in_progress' => ['blocked', 'completed', 'cancelled'],
        'blocked' => ['in_progress', 'cancelled'],
        'completed' => ['in_progress'],
        'cancelled' => [],
    ];

    public function __construct(private readonly BusinessClock $clock) {}

    public static function allows(TaskStatus $from, TaskStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value], true);
    }

    /** @return list<TaskStatus> */
    public static function targetsFrom(TaskStatus $from): array
    {
        return array_map(fn (string $s) => TaskStatus::from($s), self::ALLOWED[$from->value]);
    }

    /** @return bool true when exactly one row moved */
    public function apply(Task $task, TaskStatus $from, TaskStatus $to): bool
    {
        return Task::query()->whereKey($task->id)->where('status', $from->value)->update([
            'status' => $to->value,
            // Business time, so "completed on" follows the company clock (and the demo clock); audit time is real time.
            'completed_at' => $to === TaskStatus::Completed ? $this->clock->nowUtc() : null,
            'updated_at' => now(),
        ]) === 1;
    }
}
