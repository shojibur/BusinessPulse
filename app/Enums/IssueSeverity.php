<?php

namespace App\Enums;

/**
 * `critical` exists in the schema but is never assigned in Stage 1.
 */
enum IssueSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /** The more severe of two severities (re-detection never lowers severity). */
    public static function max(self $a, self $b): self
    {
        return $b->rank() > $a->rank() ? $b : $a;
    }
}
