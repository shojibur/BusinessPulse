<?php

namespace App\Services\Meetings;

use App\Enums\MeetingStatus;
use App\Events\Meetings\RecordingDeleted;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Removes a recording file for the retention purge and for Owner early deletion. The transcript, decisions and
 * task drafts are never touched. A file that is already missing still counts as deleted; a filesystem error leaves
 * the meeting unchanged and is logged with the meeting id only.
 */
final class RecordingDeleter
{
    public const RETENTION = 'retention';

    public const OWNER_REQUEST = 'owner_request';

    public const DELETED = 'deleted';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /** @return 'deleted'|'skipped'|'failed' */
    public function delete(Meeting $meeting, string $reason, ?int $actorUserId = null): string
    {
        $result = DB::transaction(function () use ($meeting): string {
            $row = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();

            if ($row === null || $row->recording_path === null || $row->recording_deleted_at !== null || $row->status === MeetingStatus::Transcribing) {
                return self::SKIPPED;
            }

            $disk = Storage::disk('local');

            try {
                if ($disk->exists($row->recording_path)) {
                    $disk->delete($row->recording_path);
                }

                if ($disk->exists($row->recording_path)) {
                    throw new \RuntimeException('file still present');
                }

                $disk->deleteDirectory("recordings/{$row->id}");
            } catch (Throwable) {
                Log::warning('recording_delete_failed', ['meeting_id' => $row->id, 'error' => 'filesystem_error']);

                return self::FAILED;
            }

            $row->forceFill(['recording_path' => null, 'recording_deleted_at' => now()])->save();

            return self::DELETED;
        });

        if ($result === self::DELETED) {
            RecordingDeleted::dispatch($meeting->refresh(), $reason, $actorUserId);
        }

        return $result;
    }
}
