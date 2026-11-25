<?php

namespace App\Http\Requests;

class ApproveTaskDraftRequest extends UpdateTaskDraftRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve', $this->route('taskDraft')) ?? false;
    }
}
