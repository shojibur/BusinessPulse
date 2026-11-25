<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;

/** The `tasks` prop of the Issue detail page: counts and up to 20 tasks, no descriptions, comments or excerpts; null for Staff. */
final class TasksForIssue
{
    /**
     * @return array{counts: array<string, int>, items: list<array<string, mixed>>, createUrl: string|null}|null
     */
    public function __invoke(Issue $issue, User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        $base = Task::query()->where('issue_id', $issue->id);
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $today = OverdueCondition::today();

        return [
            'counts' => array_merge(array_fill_keys(TaskStatus::values(), 0), $counts->all()),
            'items' => (clone $base)->with('assignee:id,name')->orderBy('due_date')->orderBy('id')->limit(20)->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'assigneeName' => $task->assignee->name,
                    'dueDate' => $task->due_date->format('Y-m-d'),
                    'status' => $task->status->value,
                    'statusLabel' => $task->status->label(),
                    'isOverdue' => OverdueCondition::isOverdue($task, $today),
                    'url' => route('tasks.show', $task),
                ])->all(),
            'createUrl' => null, // manual task creation is PoV Polish
        ];
    }
}
