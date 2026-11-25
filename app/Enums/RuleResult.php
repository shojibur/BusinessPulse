<?php

namespace App\Enums;

enum RuleResult: string
{
    case Breached = 'breached';
    case NotBreached = 'not_breached';
    case InsufficientData = 'insufficient_data';

    public function label(): string
    {
        return match ($this) {
            self::Breached => 'breached',
            self::NotBreached => 'not breached',
            self::InsufficientData => 'insufficient data',
        };
    }
}
