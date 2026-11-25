<?php

namespace App\Enums;

enum FreshnessState: string
{
    case Current = 'current';
    case Behind = 'behind';
}
