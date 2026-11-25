<?php

namespace App\Http\Requests;

use App\Services\Meetings\MeetingTranscriptService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('enterTranscript', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['transcript' => ['required', 'string', 'min:'.MeetingTranscriptService::MIN, 'max:'.MeetingTranscriptService::MAX]];
    }
}
