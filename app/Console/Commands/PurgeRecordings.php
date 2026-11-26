<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Services\Meetings\RecordingDeleter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recordings:purge')]
#[Description('Delete meeting recordings older than each company\'s retention period (transcripts and drafts are kept)')]
class PurgeRecordings extends Command
{
    public function handle(RecordingDeleter $deleter): int
    {
        $purged = 0;
        $failed = 0;

        Meeting::query()
            ->select('meetings.*')
            ->join('departments', 'departments.id', '=', 'meetings.department_id')
            ->join('companies', 'companies.id', '=', 'departments.company_id')
            ->whereNotNull('meetings.recording_path')
            ->whereNull('meetings.recording_deleted_at')
            ->where('meetings.status', '!=', MeetingStatus::Transcribing->value)
            // Bound application time (the same clock that stamps `recording_uploaded_at`), not the database clock.
            ->whereRaw("meetings.recording_uploaded_at < ?::timestamptz - (companies.recording_retention_days * interval '1 day')", [now()->toDateTimeString()])
            ->orderBy('meetings.id')
            ->chunkById(100, function ($chunk) use ($deleter, &$purged, &$failed): void {
                foreach ($chunk as $meeting) {
                    match ($deleter->delete($meeting, RecordingDeleter::RETENTION)) {
                        RecordingDeleter::DELETED => $purged++,
                        RecordingDeleter::FAILED => $failed++,
                        default => null,
                    };
                }
            }, 'meetings.id', 'id');

        $this->info("Purged: {$purged}. Failed: {$failed}.");

        return self::SUCCESS;
    }
}
