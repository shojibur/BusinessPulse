<?php

namespace App\Services\Intelligence;

/**
 * Returned by MetricCalculator instead of a number when the denominator is zero. It is neither a string nor
 * null, so a zero denominator can never be mistaken for a value or silently treated as zero.
 */
enum Indeterminate
{
    case ZeroDenominator;
}
