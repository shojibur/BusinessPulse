<?php

namespace App\Enums;

enum ActivityStage: string
{
    case BusinessData = 'business_data';
    case RuleEvaluation = 'rule_evaluation';
    case Issue = 'issue';
    case Brief = 'brief';
    case Meeting = 'meeting';
    case Task = 'task';
    case Outcome = 'outcome';

    public function label(): string
    {
        return match ($this) {
            self::BusinessData => 'Business data',
            self::RuleEvaluation => 'Rule evaluation',
            self::Issue => 'Issue',
            self::Brief => 'Brief',
            self::Meeting => 'Meeting',
            self::Task => 'Task',
            self::Outcome => 'Outcome',
        };
    }

    /** The stage of an action by its prefix (Requirement 14.4). */
    public static function forAction(string $action): self
    {
        return match (explode('.', $action)[0]) {
            'import' => self::BusinessData,
            'rule_evaluation', 'business_rule' => self::RuleEvaluation,
            'issue' => self::Issue,
            'brief' => self::Brief,
            'meeting' => self::Meeting,
            'task_draft', 'task' => self::Task,
            default => self::Outcome, // outcome.*, feedback.*
        };
    }
}
