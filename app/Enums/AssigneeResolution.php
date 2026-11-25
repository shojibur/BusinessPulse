<?php

namespace App\Enums;

enum AssigneeResolution: string
{
    case Resolved = 'resolved';
    case Ambiguous = 'ambiguous';
    case Unmatched = 'unmatched';
    case None = 'none';
}
