<?php

namespace App\Http\Requests;

use App\Enums\MeetingStatus;
use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $meeting = $this->route('meeting');

        return [
            'title' => ['required', 'string', 'min:1', 'max:150'],
            'meeting_date' => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) use ($meeting) {
                $processing = in_array($meeting->status, [MeetingStatus::Transcribing, MeetingStatus::Extracting], true);

                if ($processing && $value !== $meeting->meeting_date->format('Y-m-d')) {
                    $fail('The meeting date cannot be changed while the meeting is being processed.');
                }
            }],
            'issue_ids' => ['nullable', 'array', 'max:10'],
            'issue_ids.*' => ['integer', 'distinct', fn ($attribute, $value, $fail) => Issue::query()->whereKey((int) $value)->where('department_id', $meeting->department_id)->exists()
                ?: $fail('The selected issue does not belong to the meeting department.')],
        ];
    }
}
