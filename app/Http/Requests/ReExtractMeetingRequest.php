<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReExtractMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reExtract', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
