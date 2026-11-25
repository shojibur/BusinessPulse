<?php

namespace App\Events\Meetings;

use App\Models\Meeting;

/** An extraction run committed its decisions and task drafts. */
final class ExtractionCompleted extends MeetingEvent
{
    public function __construct(Meeting $meeting, public readonly int $extractionRun, public readonly int $decisionCount, public readonly int $taskDraftCount, ?int $actorUserId = null)
    {
        parent::__construct($meeting, $actorUserId);
    }
}
