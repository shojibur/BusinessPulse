<?php

namespace App\Http\Requests;

use App\Enums\MeetingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteRecordingRequest extends FormRequest
{
    /** Owner only; a Manager gets 403 from the policy. */
    public function authorize(): bool
    {
        return $this->user()?->can('deleteRecording', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $meeting = $this->route('meeting');

            if ($meeting->status === MeetingStatus::Transcribing) {
                $validator->errors()->add('recording', 'The recording is being transcribed. Try again when it has finished.');
            } elseif ($meeting->recording_path === null || $meeting->recording_deleted_at !== null) {
                $validator->errors()->add('recording', 'This meeting has no recording to delete.');
            }
        }];
    }
}
