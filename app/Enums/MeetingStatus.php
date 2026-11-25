<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case Uploaded = 'uploaded';
    case Transcribing = 'transcribing';
    case Transcribed = 'transcribed';
    case Extracting = 'extracting';
    case ReadyForReview = 'ready_for_review';
    case Failed = 'failed';

    /** The page polls while a background job owns the meeting. */
    public function polling(): bool
    {
        return in_array($this, [self::Uploaded, self::Transcribing, self::Extracting, self::Transcribed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Uploaded => 'Uploaded',
            self::Transcribing => 'Transcribing',
            self::Transcribed => 'Transcribed',
            self::Extracting => 'Extracting tasks',
            self::ReadyForReview => 'Ready for review',
            self::Failed => 'Failed',
        };
    }
}
