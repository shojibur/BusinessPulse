<?php

namespace App\Enums;

enum CoverageStatus: string
{
    case Sufficient = 'sufficient';
    case Partial = 'partial';
    case Stale = 'stale';
}
