<?php

namespace App\Events\Meetings;

use App\Models\Meeting;

/** A transcript was stored (audio or manual). */
final class TranscriptReady extends MeetingEvent
{
    public function __construct(Meeting $meeting, public readonly string $transcriptSource, ?int $actorUserId = null)
    {
        parent::__construct($meeting, $actorUserId);
    }
}
