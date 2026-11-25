<?php

namespace App\Services\Tasks;

use App\Enums\AssigneeResolution;
use App\Enums\DeadlineResolution;
use App\Models\Meeting;
use App\Models\TaskDraft;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The single implementation of the reviewable-field rules, used by both Update and Approve. A key present with value
 * null clears the field; an absent key leaves it unchanged. The AI suggestion columns (suggested_assignee_name,
 * suggested_due_date_text, suggested_due_date, transcript_excerpt) and every workflow column are never written here.
 */
final class DraftEditor
{
    /**
     * @param  array<string, mixed>  $edits  only keys: title, description, assignee_id, due_date, issue_id
     *
     * @throws ValidationException
     */
    public function apply(TaskDraft $draft, Meeting $meeting, array $edits): DraftValues
    {
        $values = new DraftValues(
            (string) $draft->suggested_title,
            (string) $draft->suggested_description,
            $draft->assignee_id,
            $draft->assignee_resolution,
            $draft->due_date === null ? null : CarbonImmutable::parse($draft->due_date->format('Y-m-d')),
            $draft->deadline_resolution,
            $draft->issue_id,
        );
        $errors = [];

        if (array_key_exists('title', $edits)) {
            $title = trim((string) $edits['title']);
            mb_strlen($title) >= 1 && mb_strlen($title) <= 150 ? $values->title = $title : $errors['title'] = 'The title must be 1 to 150 characters.';
        }

        if (array_key_exists('description', $edits)) {
            $description = trim((string) ($edits['description'] ?? ''));
            mb_strlen($description) <= 2000 ? $values->description = $description : $errors['description'] = 'The description must be at most 2,000 characters.';
        }

        if (array_key_exists('assignee_id', $edits)) {
            if ($edits['assignee_id'] === null || $edits['assignee_id'] === '') {
                $values->assigneeId = null;
                $values->assigneeResolution = AssigneeResolution::None;
            } elseif (is_numeric($edits['assignee_id']) && AllowedAssignees::contains($meeting->department_id, (int) $edits['assignee_id'])) {
                $values->assigneeId = (int) $edits['assignee_id'];
                $values->assigneeResolution = AssigneeResolution::Resolved;
            } else {
                $errors['assignee_id'] = 'Choose an active employee of this department.';
            }
        }

        if (array_key_exists('due_date', $edits)) {
            if ($edits['due_date'] === null || $edits['due_date'] === '') {
                $values->dueDate = null;
                $values->deadlineResolution = DeadlineResolution::None;
            } else {
                $date = is_string($edits['due_date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $edits['due_date']) : false;

                if ($date !== false && $date->format('Y-m-d') === $edits['due_date']) {
                    $values->dueDate = $date;
                    $values->deadlineResolution = DeadlineResolution::Resolved;
                } else {
                    $errors['due_date'] = 'Enter a valid date.';
                }
            }
        }

        if (array_key_exists('issue_id', $edits)) {
            if ($edits['issue_id'] === null || $edits['issue_id'] === '') {
                $values->issueId = null;
            } elseif (is_numeric($edits['issue_id']) && $meeting->issues()->where('issues.id', (int) $edits['issue_id'])->exists()) {
                $values->issueId = (int) $edits['issue_id'];
            } else {
                $errors['issue_id'] = 'The issue must be one of the issues linked to this meeting.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /**
     * The approval gate: every field the Task needs must be present and valid NOW. An ambiguous or unmatched
     * assignee, or an ambiguous or missing deadline, cannot pass without a person having chosen a value.
     *
     * @throws ValidationException
     */
    public function assertApprovable(DraftValues $values, Meeting $meeting): void
    {
        $errors = [];

        if (trim($values->title) === '' || mb_strlen($values->title) > 150) {
            $errors['title'] = 'The title must be 1 to 150 characters.';
        }

        if ($values->assigneeId === null) {
            $errors['assignee_id'] = $values->assigneeResolution === AssigneeResolution::Ambiguous
                ? 'Several employees match this name. Choose one.'
                : 'Choose an employee for this task.';
        } elseif (! AllowedAssignees::contains($meeting->department_id, $values->assigneeId)) {
            $errors['assignee_id'] = 'This person can no longer be assigned. Choose an active employee of this department.';
        }

        if ($values->dueDate === null) {
            $errors['due_date'] = 'Choose a deadline for this task.';
        } elseif (($dateErrors = TaskDueDateRule::check($values->dueDate, $meeting->meeting_date)) !== []) {
            $errors += $dateErrors;
        }

        if ($values->issueId !== null && ! $meeting->issues()->where('issues.id', $values->issueId)->exists()) {
            $errors['issue_id'] = 'The issue must be one of the issues linked to this meeting.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
