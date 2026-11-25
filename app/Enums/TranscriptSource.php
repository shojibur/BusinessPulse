<?php

namespace App\Enums;

enum TranscriptSource: string
{
    case Audio = 'audio';
    case Manual = 'manual';
}
