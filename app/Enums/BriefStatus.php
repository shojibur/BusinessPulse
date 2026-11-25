<?php

namespace App\Enums;

enum BriefStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function inProgress(): bool
    {
        return $this === self::Queued || $this === self::Generating;
    }
}
