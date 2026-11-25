<?php

namespace App\Enums;

/**
 * Issue lifecycle (docs/stage-1-refinements.md R4). Allowed transitions only; see IssueLifecycle.
 */
enum IssueStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case ActionInProgress = 'action_in_progress';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::ActionInProgress, self::Resolved],
            self::Acknowledged => [self::ActionInProgress, self::Resolved],
            self::ActionInProgress => [self::Monitoring, self::Resolved],
            self::Monitoring => [self::ActionInProgress, self::Resolved, self::Open],
            self::Resolved => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::ActionInProgress => 'Action in progress',
            self::Monitoring => 'Monitoring',
            self::Resolved => 'Resolved',
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
