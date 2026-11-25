<?php

namespace App\Services\Meetings;

use App\Enums\MeetingErrorCode;
use RuntimeException;

/** A terminal failure of one processing stage, carrying only a closed code. */
final class MeetingStageException extends RuntimeException
{
    public function __construct(public readonly MeetingErrorCode $errorCode)
    {
        parent::__construct('Meeting stage failed ('.$errorCode->value.').');
    }
}
