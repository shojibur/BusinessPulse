<?php

namespace App\Enums;

enum ImportRowResult: string
{
    case New = 'new';
    case Corrected = 'corrected';
    case Unchanged = 'unchanged';
    case Invalid = 'invalid';
}
