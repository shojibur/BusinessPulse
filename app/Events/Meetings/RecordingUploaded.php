<?php

namespace App\Events\Meetings;

use App\Models\Meeting;

/** PoV Polish event: a recording upload was accepted. */
final class RecordingUploaded extends MeetingEvent
{
    public function __construct(Meeting $meeting, public readonly int $sizeBytes, public readonly string $mime, ?int $actorUserId = null)
    {
        parent::__construct($meeting, $actorUserId);
    }
}
