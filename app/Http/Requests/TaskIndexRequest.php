<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Task::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function status(): ?TaskStatus
    {
        return TaskStatus::tryFrom((string) $this->query('status'));
    }

    /** "open" is the combined filter of pending, in progress and blocked used by the dashboard link. */
    public function openOnly(): bool
    {
        return $this->query('status') === 'open';
    }

    public function overdue(): bool
    {
        return filter_var($this->query('overdue'), FILTER_VALIDATE_BOOLEAN);
    }

    public function assignee(): ?int
    {
        return $this->filled('assignee') && ctype_digit((string) $this->query('assignee')) ? (int) $this->query('assignee') : null;
    }
}
