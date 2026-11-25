<?php

namespace App\Enums;

enum OutcomeResult: string
{
    case Improved = 'improved';
    case Unchanged = 'unchanged';
    case Worsened = 'worsened';
    case NotEvaluable = 'not_evaluable';
}
