<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Overdue is computed at read time: an open task whose due date is strictly before the business date. It is never a
 * stored status. One class supplies the list filter, the badge, the My Tasks grouping and TaskCounts.
 */
final class OverdueCondition
{
    public const OPEN = ['pending', 'in_progress', 'blocked'];

    public static function today(): CarbonInterface
    {
        return app(BusinessClock::class)->today();
    }

    /**
     * @param  Builder|EloquentBuilder<Task>  $query
     * @return Builder|EloquentBuilder<Task>
     */
    public static function apply($query, ?CarbonInterface $today = null)
    {
        return $query->whereIn('tasks.status', self::OPEN)->where('tasks.due_date', '<', ($today ?? self::today())->toDateString());
    }

    /** Boolean SQL expression; bind the business date for its single `?`. */
    public static function sql(): string
    {
        return "(tasks.status IN ('pending','in_progress','blocked') AND tasks.due_date < ?::date)";
    }

    public static function isOverdue(Task $task, ?CarbonInterface $today = null): bool
    {
        return $task->status->isOpen() && $task->due_date->format('Y-m-d') < ($today ?? self::today())->format('Y-m-d');
    }

    public static function daysOverdue(Task $task, ?CarbonInterface $today = null): ?int
    {
        $today ??= self::today();

        return self::isOverdue($task, $today)
            ? (int) abs(CarbonImmutable::parse($task->due_date->format('Y-m-d'), 'UTC')->diffInDays(CarbonImmutable::parse($today->format('Y-m-d'), 'UTC')))
            : null;
    }
}
