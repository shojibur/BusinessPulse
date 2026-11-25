<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectTaskDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('taskDraft')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
