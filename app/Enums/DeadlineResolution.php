<?php

namespace App\Enums;

enum DeadlineResolution: string
{
    case Resolved = 'resolved';
    case Ambiguous = 'ambiguous';
    case None = 'none';
}
