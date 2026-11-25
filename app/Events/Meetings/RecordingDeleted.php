<?php

namespace App\Events\Meetings;

use App\Models\Meeting;

/** PoV Polish event: a recording file was removed (retention or Owner request). */
final class RecordingDeleted extends MeetingEvent
{
    public function __construct(Meeting $meeting, public readonly string $reason, ?int $actorUserId = null)
    {
        parent::__construct($meeting, $actorUserId);
    }
}
