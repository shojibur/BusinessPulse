<?php

namespace App\Enums;

enum InsufficientReason: string
{
    case IncompletePeriod = 'incomplete_period';
    case PeriodAbsent = 'period_absent';
    case ZeroDenominator = 'zero_denominator';
    case NoRecords = 'no_records';

    public function label(): string
    {
        return match ($this) {
            self::IncompletePeriod => 'the week is incomplete',
            self::PeriodAbsent => 'no sales data for the week',
            self::ZeroDenominator => 'the comparison value is zero',
            self::NoRecords => 'no sales data yet',
        };
    }
}
