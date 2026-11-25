<?php

namespace App\Http\Requests;

use App\Enums\MeetingStatus;
use App\Services\Meetings\RecordingStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadRecording', $this->route('meeting')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Consent is required before any audio is stored, let alone sent to a provider.
            'recording_consent' => ['required', 'accepted'],
            'file' => [
                'required', 'file',
                'min:1', 'max:'.RecordingStorage::MAX_KILOBYTES,
                'extensions:'.implode(',', RecordingStorage::EXTENSIONS),
                fn ($attribute, $value, $fail) => $this->mimeMatchesExtension($value) ?: $fail('The file content does not match its extension.'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recording_consent.accepted' => 'Confirm that you have permission to record and to send the audio to the AI provider.',
            'recording_consent.required' => 'Confirm that you have permission to record and to send the audio to the AI provider.',
            'file.max' => 'The recording must be 20 MB or smaller.',
            'file.min' => 'The recording file is empty.',
            'file.extensions' => 'The recording must be an mp3, m4a, wav, webm or mp4 file.',
            'file.required' => 'Choose a recording file (up to 20 MB).',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $meeting = $this->route('meeting');
            $allowed = $meeting->status === MeetingStatus::Draft
                || ($meeting->status === MeetingStatus::Failed && $meeting->failure_stage?->value === 'transcription');

            if (! $allowed || ($meeting->recording_path !== null && $meeting->recording_deleted_at === null) || $meeting->transcript !== null) {
                $validator->errors()->add('file', 'A recording cannot be uploaded for this meeting in its current state.');
            }
        }];
    }

    /** The content-detected MIME type (never the client-sent one), or null. */
    public function detectedMime(): ?string
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        return $mime === false ? null : $mime;
    }

    private function mimeMatchesExtension(mixed $file): bool
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return false;
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        return in_array($detected, RecordingStorage::MIME_BY_EXTENSION[strtolower($file->getClientOriginalExtension())] ?? [], true);
    }
}
