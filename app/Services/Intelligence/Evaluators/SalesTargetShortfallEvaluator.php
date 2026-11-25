<?php

namespace App\Services\Intelligence\Evaluators;

use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleKey;
use App\Enums\RuleResult;
use App\Enums\WeekCompleteness;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Services\Intelligence\EvaluationContext;
use App\Services\Intelligence\EvaluationOutcome;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;

/**
 * Target achievement of the latest closed week. Breach is achievement strictly below the threshold; high
 * severity strictly below `high_below_pct`. An open or incomplete week is never evaluated.
 */
final class SalesTargetShortfallEvaluator implements RuleEvaluator
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    public function key(): RuleKey
    {
        return RuleKey::SalesTargetShortfall;
    }

    public function evaluate(BusinessRule $rule, Department $department, EvaluationContext $context): EvaluationOutcome
    {
        $threshold = (string) $rule->threshold;
        $highBelow = $rule->params['high_below_pct'] ?? '70.00';
        $week = $context->latest;

        $base = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $this->key()->metricName(),
            $week->weekStart, $week->weekEnd, null, null, null, null, null, null, [], [], [], [],
        );

        if ($context->noRecords) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::NoRecords);
        }

        $totals = $this->metrics->weekTotals($department->id, $week->weekStart, $context->calendar['trading_days'], $context->expectedBranches);

        $base = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $base->metricName, $base->periodStart, $base->periodEnd, null, null,
            null, null, null, null,
            ['current' => [
                'start' => $week->weekStart->format('Y-m-d'),
                'end' => $week->weekEnd->format('Y-m-d'),
                'status' => $week->status->value,
                'completeness' => $week->completeness?->value,
                'revenue' => $totals->revenue,
                'target' => $totals->target,
            ]],
            [
                'expectedBranches' => $context->expectedBranches,
                'current' => [
                    'expectedDueBranchDays' => $week->dueBranchDays,
                    'expectedBranchDays' => $week->expectedBranchDays,
                    'presentBranchDays' => $week->presentBranchDays,
                    'coveragePct' => $week->coveragePct,
                    'missingDueBranchDays' => array_map(fn ($gap) => [
                        'branch_code' => $gap->branchCode,
                        'date' => $gap->date->format('Y-m-d'),
                        'kind' => $gap->kind,
                    ], $week->gaps),
                ],
            ],
            $totals->recordIds, $totals->importIds,
        );

        if ($week->completeness === WeekCompleteness::Absent) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::PeriodAbsent);
        }

        if ($week->completeness === WeekCompleteness::Incomplete) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::IncompletePeriod);
        }

        $achievement = $this->metrics->targetAchievementPct($totals->revenue, $totals->target);
        if ($achievement === Indeterminate::ZeroDenominator) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::ZeroDenominator);
        }

        $measured = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $base->metricName, $base->periodStart, $base->periodEnd, null, null,
            $totals->revenue, $totals->target, bcsub($totals->revenue, $totals->target, 2), $achievement,
            $base->periods, $base->coverage, $base->recordIds, $base->importIds,
        );

        if (bccomp($achievement, $threshold, 4) < 0) {
            return $measured->with(
                RuleResult::Breached,
                bccomp($achievement, $highBelow, 4) < 0 ? IssueSeverity::High : IssueSeverity::Medium,
            );
        }

        return $measured->with(RuleResult::NotBreached);
    }
}
