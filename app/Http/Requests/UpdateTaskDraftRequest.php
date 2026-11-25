<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('taskDraft')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'issue_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Only the whitelisted keys; "null clears" stays apart from "absent leaves unchanged". meeting_id, task_id,
     * status and every workflow column in the body are ignored.
     *
     * @return array<string, mixed>
     */
    public function edits(): array
    {
        return array_intersect_key($this->validated(), array_flip(['title', 'description', 'assignee_id', 'due_date', 'issue_id']));
    }
}
