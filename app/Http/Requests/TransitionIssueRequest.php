<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the reason is read from the request. Whether a reason is required depends on the transition and is
 * enforced by IssueLifecycle (empty after trimming is rejected for manual monitoring and manual resolve).
 */
class TransitionIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the issue (policy).
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
