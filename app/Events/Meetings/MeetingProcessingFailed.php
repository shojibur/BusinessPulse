<?php

namespace App\Events\Meetings;

use App\Models\Meeting;

/** A processing stage failed. Carries the stage and a closed error code only. */
final class MeetingProcessingFailed extends MeetingEvent
{
    public function __construct(Meeting $meeting, public readonly string $stage, public readonly string $errorCode, ?int $actorUserId = null)
    {
        parent::__construct($meeting, $actorUserId);
    }
}
