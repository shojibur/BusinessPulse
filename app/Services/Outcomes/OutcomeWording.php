<?php

namespace App\Services\Outcomes;

use App\Enums\NotEvaluableReason;
use App\Enums\OutcomeMetric;
use App\Enums\OutcomePhase;
use App\Enums\OutcomeResult;

/**
 * Fixed wording and number formatters for outcomes. Neutral: it reports observed change after actions and never says an
 * action caused, produced or explains a change.
 */
final class OutcomeWording
{
    public const HEADING = 'Observed change after actions';

    public const STANDING = 'This shows what changed after actions were taken. It does not show that the actions caused the change.';

    public static function result(OutcomeResult $result): string
    {
        return match ($result) {
            OutcomeResult::Improved => 'Improved',
            OutcomeResult::Unchanged => 'Unchanged',
            OutcomeResult::Worsened => 'Worsened',
            OutcomeResult::NotEvaluable => 'Not evaluable',
        };
    }

    public static function metric(OutcomeMetric $metric): string
    {
        return match ($metric) {
            OutcomeMetric::WeeklyRevenue => 'Weekly revenue',
            OutcomeMetric::TargetAchievement => 'Target achievement %',
            OutcomeMetric::Coverage => 'Coverage %',
        };
    }

    public static function phase(OutcomePhase $phase): string
    {
        return match ($phase) {
            OutcomePhase::PreAction => 'Before action — not used for verification',
            OutcomePhase::DuringAction => 'During action — not used for verification',
            OutcomePhase::PostAction => 'After action — used for verification',
        };
    }

    public static function reason(?NotEvaluableReason $reason): ?string
    {
        return match ($reason) {
            null => null,
            NotEvaluableReason::BaselineInsufficientData => 'The baseline week did not have enough data to compare.',
            NotEvaluableReason::FollowupNotAfterBaseline => 'The latest evaluation is not for a later period than the baseline.',
            NotEvaluableReason::FollowupInsufficientData => 'The follow-up week did not have enough data to compare.',
            NotEvaluableReason::FollowupPeriodPartial => 'The follow-up week is not complete yet, so it was not compared.',
            NotEvaluableReason::BranchesChanged => 'The branches reported in the follow-up week differ from the baseline week.',
            NotEvaluableReason::ZeroBaselineValue => 'The baseline revenue was zero, so a relative change cannot be calculated.',
        };
    }

    public static function check(string $name): string
    {
        return match ($name) {
            'baseline_sufficient' => 'Baseline week has enough data',
            'followup_after_baseline' => 'Follow-up is for a later period',
            'followup_sufficient' => 'Follow-up week has enough data',
            'followup_week_complete' => 'Follow-up week is closed and complete',
            'same_branches' => 'Same branches reported',
            'baseline_value_nonzero' => 'Baseline revenue is not zero',
            default => $name,
        };
    }

    public static function money(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', ltrim(bcadd($value, '0', 2), '-'), 2), 2, '00');

        return (str_starts_with($value, '-') ? '-' : '').'MYR '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }

    public static function signedMoney(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (str_starts_with($value, '-') ? '' : '+').self::money($value);
    }

    public static function signedPercent(?string $value): ?string
    {
        return $value === null ? null : (str_starts_with($value, '-') ? '' : '+').$value.'%';
    }

    public static function signedPoints(?string $value): ?string
    {
        return $value === null ? null : (str_starts_with($value, '-') ? '' : '+').$value.' pts';
    }
}
