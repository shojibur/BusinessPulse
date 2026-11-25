<?php

namespace App\Enums;

/** Completeness of a closed reporting week. An open week has none. */
enum WeekCompleteness: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
    case Absent = 'absent';
}
