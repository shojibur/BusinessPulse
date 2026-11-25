<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller authorizes against the resolved target
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['useful' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']];
    }
}
