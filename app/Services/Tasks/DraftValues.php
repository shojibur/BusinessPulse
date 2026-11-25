<?php

namespace App\Services\Tasks;

use App\Enums\AssigneeResolution;
use App\Enums\DeadlineResolution;
use Carbon\CarbonImmutable;

/** The reviewable fields of a draft after edits have been applied (not yet saved). */
final class DraftValues
{
    public function __construct(
        public string $title,
        public string $description,
        public ?int $assigneeId,
        public AssigneeResolution $assigneeResolution,
        public ?CarbonImmutable $dueDate,
        public DeadlineResolution $deadlineResolution,
        public ?int $issueId,
    ) {}

    /** @return array<string, mixed> columns of task_drafts */
    public function draftColumns(): array
    {
        return [
            'suggested_title' => $this->title,
            'suggested_description' => $this->description,
            'assignee_id' => $this->assigneeId,
            'assignee_resolution' => $this->assigneeResolution->value,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'deadline_resolution' => $this->deadlineResolution->value,
            'issue_id' => $this->issueId,
        ];
    }
}
