<?php

namespace App\Enums;

/** A reporting week is closed when every trading day in it is due, otherwise open. */
enum WeekStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
