<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('retry', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
