<?php

namespace App\Enums;

enum AudioStatus: string
{
    case None = 'none';
    case Queued = 'queued';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    public function inProgress(): bool
    {
        return $this === self::Queued || $this === self::Generating;
    }
}
