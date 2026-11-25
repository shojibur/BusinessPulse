<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('retry', $this->route('brief')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
