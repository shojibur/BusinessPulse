<?php

namespace App\Services\Meetings;

use App\Enums\FailureStage;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a recording on the PRIVATE local disk under recordings/{meeting id}/ with a random server-made name. The
 * stored path never contains user input; the original name is sanitized display data only. No public URL exists.
 */
final class RecordingStorage
{
    public const EXTENSIONS = ['mp3', 'm4a', 'wav', 'webm', 'mp4'];

    public const MAX_KILOBYTES = 20480;

    /** Detected MIME types accepted per extension. */
    public const MIME_BY_EXTENSION = [
        'mp3' => ['audio/mpeg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'webm' => ['audio/webm', 'video/webm'],
        'mp4' => ['video/mp4', 'audio/mp4'],
    ];

    public function __construct(private readonly MeetingStateMachine $states) {}

    /**
     * Stores the file and moves the meeting to `uploaded` in one compare-and-set, persisting consent first-hand
     * (time and user). Returns false (and removes the file) when another request already moved the meeting.
     */
    public function accept(Meeting $meeting, UploadedFile $file, User $actor, string $detectedMime): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = Storage::disk('local')->putFileAs("recordings/{$meeting->id}", $file, Str::random(40).'.'.$extension);

        if ($path === false) {
            return false;
        }

        $attributes = [
            'recording_path' => $path,
            'recording_original_name' => $this->sanitizeName($file->getClientOriginalName()),
            'recording_mime' => $detectedMime,
            'recording_size' => $file->getSize(),
            'recording_uploaded_at' => now(),
            'recording_consent_at' => now(),
            'recording_consent_by' => $actor->id,
            'recording_deleted_at' => null,
            'error_message' => null,
        ];
        $where = ['recording_path' => null, 'transcript' => null];

        $moved = $this->states->transition($meeting->id, MeetingStatus::Draft, MeetingStatus::Uploaded, $attributes, null, $where)
            || $this->states->transition($meeting->id, MeetingStatus::Failed, MeetingStatus::Uploaded, $attributes, FailureStage::Transcription, $where);

        if (! $moved) {
            Storage::disk('local')->delete($path);
        }

        return $moved;
    }

    /** No path separators or control characters, at most 255 characters, never empty. */
    public function sanitizeName(string $name): string
    {
        $name = str_replace(['\\', '/'], '_', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name, '. ');
        $name = mb_substr($name, 0, 255);

        return $name === '' ? 'recording' : $name;
    }
}
