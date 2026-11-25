<?php

namespace App\Services\Meetings;

use App\Enums\FailureStage;
use App\Enums\MeetingStatus;
use App\Events\Meetings\TranscriptEdited;
use App\Events\Meetings\TranscriptReady;
use App\Jobs\ExtractMeetingTasksJob;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manual transcript entry (the documented fallback when transcription fails or no recording exists) and transcript
 * editing. Extraction always reads the CURRENT transcript when a job starts.
 */
final class MeetingTranscriptService
{
    public const MIN = 20;

    public const MAX = 60000;

    public function __construct(private readonly MeetingStateMachine $states) {}

    /** @return bool false when another request or a running job already owns the meeting */
    public function storeManual(Meeting $meeting, User $actor, string $text): bool
    {
        $text = $this->clean($text);

        return DB::transaction(function () use ($meeting, $actor, $text): bool {
            $attributes = ['transcript' => $text, 'transcript_source' => 'manual', 'transcript_edited_at' => null];

            // A failed EXTRACTION already has a transcript and must be edited, not replaced through this path.
            $moved = $this->states->transition($meeting->id, MeetingStatus::Draft, MeetingStatus::Transcribed, $attributes, null, ['transcript' => null])
                || $this->states->transition($meeting->id, MeetingStatus::Failed, MeetingStatus::Transcribed, $attributes, FailureStage::Transcription);

            if (! $moved) {
                return false;
            }

            TranscriptReady::dispatch($meeting->fresh(), 'manual', $actor->id);
            ExtractMeetingTasksJob::dispatch($meeting->id, $actor->id);

            return true;
        });
    }

    /**
     * Edits the transcript. The status never changes: the drafts stay, and "changed since the last extraction" is
     * derived from the timestamps. Returns false when the text is identical (a no-op).
     *
     * @return bool|null null when the meeting is in a state that does not allow editing
     */
    public function edit(Meeting $meeting, User $actor, string $text): ?bool
    {
        $text = $this->clean($text);

        return DB::transaction(function () use ($meeting, $actor, $text): ?bool {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            if ($locked->transcript === null || in_array($locked->status, [MeetingStatus::Draft, MeetingStatus::Uploaded, MeetingStatus::Transcribing, MeetingStatus::Extracting], true)) {
                return null;
            }

            if ($text === $locked->transcript) {
                return false;
            }

            $locked->forceFill(['transcript' => $text, 'transcript_edited_at' => now()])->save();
            TranscriptEdited::dispatch($locked->fresh(), $actor->id);

            return true;
        });
    }

    public function clean(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }
}
