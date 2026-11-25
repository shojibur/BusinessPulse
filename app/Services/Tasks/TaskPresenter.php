<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use App\Services\Intelligence\IssueEvidence;
use App\Services\Intelligence\MetricCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Explicit Inertia props for task pages. Staff get the minimum: their own task, its supporting quote, and nothing that
 * links to or reveals confidential meeting, issue evidence or brief data.
 */
final class TaskPresenter
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    /**
     * @return array<string, mixed>
     */
    public function row(Task $task, User $viewer, CarbonInterface $today): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'assignee' => ['id' => $task->assignee_id, 'name' => $task->assignee->name],
            'department' => ['id' => $task->department_id, 'name' => $task->department->name],
            'dueDate' => $task->due_date->format('Y-m-d'),
            'status' => $task->status->value,
            'statusLabel' => $task->status->label(),
            'isOverdue' => OverdueCondition::isOverdue($task, $today),
            'daysOverdue' => OverdueCondition::daysOverdue($task, $today),
            'issue' => $viewer->isStaff() || $task->issue === null ? null : ['id' => $task->issue->id, 'title' => $task->issue->title],
            'allowedTransitions' => $this->allowedTransitions($task, $viewer),
            'url' => route('tasks.show', $task),
        ];
    }

    /**
     * Targets this actor may use (the machine's targets filtered by the policy).
     *
     * @return list<string>
     */
    public function allowedTransitions(Task $task, User $viewer): array
    {
        return collect(TaskStatusMachine::targetsFrom($task->status))
            ->filter(fn (TaskStatus $to) => Gate::forUser($viewer)->allows('changeStatus', [$task, $to]))
            ->map(fn (TaskStatus $to) => $to->value)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Task $task, User $viewer, CarbonInterface $today): array
    {
        $task->loadMissing(['assignee', 'assigner', 'department.company', 'meeting', 'issue', 'draft', 'comments.author']);
        $timezone = $task->department->company->timezone;
        $local = fn ($time) => $time?->setTimezone($timezone)->toIso8601String();
        $staff = $viewer->isStaff();

        $issue = $task->issue;

        return [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status->value,
                'statusLabel' => $task->status->label(),
                'dueDate' => $task->due_date->format('Y-m-d'),
                'isOverdue' => OverdueCondition::isOverdue($task, $today),
                'daysOverdue' => OverdueCondition::daysOverdue($task, $today),
                'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                'assignedBy' => ['id' => $task->assigner->id, 'name' => $task->assigner->name],
                'department' => ['id' => $task->department->id, 'name' => $task->department->name],
                'createdAt' => $local($task->created_at),
                'updatedAt' => $local($task->updated_at),
                'completedAt' => $local($task->completed_at),
            ],
            'meeting' => $task->meeting === null ? null : [
                'id' => $task->meeting->id,
                'title' => $task->meeting->title,
                'meetingDate' => $task->meeting->meeting_date->format('Y-m-d'),
                'url' => $staff ? null : route('meetings.show', $task->meeting),
            ],
            // The draft's own short supporting quote: the evidence the assignment is based on.
            'excerpt' => $task->draft?->transcript_excerpt,
            'decisions' => $staff ? null : ($task->meeting?->decisions ?? []),
            'issue' => $issue === null ? null : $this->issue($issue, $staff),
            'comments' => $task->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author' => ['name' => $comment->author->name, 'role' => $comment->author->role->value],
                'createdAt' => $local($comment->created_at),
            ])->values()->all(),
            'can' => [
                'changeStatus' => $this->allowedTransitions($task, $viewer),
                'comment' => true,
            ],
            'businessToday' => $today->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(Issue $issue, bool $staff): array
    {
        $resolved = $issue->status->value === 'resolved';
        $base = [
            'id' => $issue->id,
            'title' => $issue->title,
            'status' => $issue->status->value,
            'resolvedNotice' => $resolved ? 'Linked issue already resolved; outcome unchanged.' : null,
        ];

        if ($staff) {
            return $base + ['severity' => null, 'url' => null, 'evidence' => null];
        }

        $evidence = IssueEvidence::forIssue($issue);
        $pct = fn (?string $value) => $value === null ? '—' : $this->metrics->display2($value).'%';

        return $base + [
            'severity' => $issue->severity->value,
            'url' => route('issues.show', $issue),
            // Percentages only (all three S04 primary metrics are percentages): no currency, import ids or details.
            'evidence' => ['metricName' => $evidence->primaryMetricName, 'baseline' => $pct($evidence->baselinePrimaryValue), 'latest' => $pct($evidence->latestPrimaryValue)],
        ];
    }
}
