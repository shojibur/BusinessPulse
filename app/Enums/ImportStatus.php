<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Allowed transitions (requirement 11.1).
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Uploaded => [self::Validated, self::Failed, self::Cancelled],
            self::Validated => [self::Processing, self::Cancelled],
            self::Processing => [self::Completed, self::CompletedWithErrors, self::Failed],
            self::Failed => [self::Processing],
            self::Completed, self::CompletedWithErrors, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Validated => 'Ready to confirm',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with errors',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
