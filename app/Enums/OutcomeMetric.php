<?php

namespace App\Enums;

/** The outcome metric per rule (R3): revenue level in MYR for the decline rule, percentages for the others. */
enum OutcomeMetric: string
{
    case WeeklyRevenue = 'weekly_revenue';
    case TargetAchievement = 'target_achievement';
    case Coverage = 'coverage';

    public static function forRuleKey(RuleKey $key): self
    {
        return match ($key) {
            RuleKey::WeeklyRevenueDecline => self::WeeklyRevenue,
            RuleKey::SalesTargetShortfall => self::TargetAchievement,
            RuleKey::MissingSalesData => self::Coverage,
        };
    }

    public function isMoney(): bool
    {
        return $this === self::WeeklyRevenue;
    }
}
