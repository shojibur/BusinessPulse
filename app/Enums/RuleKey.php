<?php

namespace App\Enums;

enum RuleKey: string
{
    case WeeklyRevenueDecline = 'weekly_revenue_decline';
    case SalesTargetShortfall = 'sales_target_shortfall';
    case MissingSalesData = 'missing_sales_data';

    /** The fixed Issue title of the rule. */
    public function issueTitle(): string
    {
        return match ($this) {
            self::WeeklyRevenueDecline => 'Weekly revenue decline',
            self::SalesTargetShortfall => 'Sales target shortfall',
            self::MissingSalesData => 'Missing sales data',
        };
    }

    public function metricName(): string
    {
        return match ($this) {
            self::WeeklyRevenueDecline => 'weekly_change_pct',
            self::SalesTargetShortfall => 'target_achievement_pct',
            self::MissingSalesData => 'coverage_pct',
        };
    }

    /** Short description shown next to the rule. */
    public function description(): string
    {
        return match ($this) {
            self::WeeklyRevenueDecline => 'Revenue of the latest closed week fell compared with the week before.',
            self::SalesTargetShortfall => 'Revenue of the latest closed week is below the weekly target.',
            self::MissingSalesData => 'Expected branch sales for due days have not been received.',
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
