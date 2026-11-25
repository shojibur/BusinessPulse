<?php

namespace App\Enums;

enum FailureStage: string
{
    case Transcription = 'transcription';
    case Extraction = 'extraction';
}
