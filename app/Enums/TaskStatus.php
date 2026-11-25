<?php

namespace App\Enums;

/** Task lifecycle. "Overdue" is NOT a status: it is computed from the due date and the business date. */
enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Pending, self::InProgress, self::Blocked];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::Blocked => 'Blocked',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
