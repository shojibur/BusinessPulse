<?php

namespace App\Services\Intelligence\Evaluators;

use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleKey;
use App\Enums\RuleResult;
use App\Enums\WeekCompleteness;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Services\Imports\WeekState;
use App\Services\Intelligence\EvaluationContext;
use App\Services\Intelligence\EvaluationOutcome;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;
use App\Services\Intelligence\WeekTotals;

/**
 * Latest closed week against the week before it. Only two complete closed weeks are compared; an open week
 * is never evaluated and there is no fallback to an older week. Breach is a drop strictly greater than the
 * threshold; high severity at or above `high_drop_pct`.
 */
final class WeeklyRevenueDeclineEvaluator implements RuleEvaluator
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    public function key(): RuleKey
    {
        return RuleKey::WeeklyRevenueDecline;
    }

    public function evaluate(BusinessRule $rule, Department $department, EvaluationContext $context): EvaluationOutcome
    {
        $threshold = (string) $rule->threshold;
        $high = $rule->params['high_drop_pct'] ?? '25.00';
        $latest = $context->latest;
        $preceding = $context->preceding;

        $base = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $this->key()->metricName(),
            $latest->weekStart, $latest->weekEnd, $preceding->weekStart, $preceding->weekEnd,
            null, null, null, null, [], [], [], [],
        );

        if ($context->noRecords) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::NoRecords);
        }

        // Ids of both weeks always feed the run key, so a data change is never hidden behind a reuse.
        $current = $this->metrics->weekTotals($department->id, $latest->weekStart, $context->calendar['trading_days'], $context->expectedBranches);
        $previous = $this->metrics->weekTotals($department->id, $preceding->weekStart, $context->calendar['trading_days'], $context->expectedBranches);

        $base = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $base->metricName, $base->periodStart, $base->periodEnd,
            $base->comparisonStart, $base->comparisonEnd, null, null, null, null,
            ['current' => $this->period($latest, $current), 'comparison' => $this->period($preceding, $previous)],
            $this->coverage($latest, $preceding, $context),
            array_values(array_unique([...$current->recordIds, ...$previous->recordIds])),
            array_values(array_unique([...$current->importIds, ...$previous->importIds])),
        );

        $reason = $this->incompleteReason($latest) ?? $this->incompleteReason($preceding);
        if ($reason !== null) {
            return $base->with(RuleResult::InsufficientData, null, $reason);
        }

        $change = $this->metrics->weeklyChangePct($current->revenue, $previous->revenue);
        if ($change === Indeterminate::ZeroDenominator) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::ZeroDenominator);
        }

        $measured = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $base->metricName, $base->periodStart, $base->periodEnd,
            $base->comparisonStart, $base->comparisonEnd,
            $current->revenue, $previous->revenue, bcsub($current->revenue, $previous->revenue, 2), $change,
            $base->periods, $base->coverage, $base->recordIds, $base->importIds,
        );

        // A drop strictly greater than the threshold breaches (change below minus threshold).
        if (bccomp($change, '-'.$threshold, 4) < 0) {
            $severity = bccomp($change, '-'.$high, 4) <= 0 ? IssueSeverity::High : IssueSeverity::Medium;

            return $measured->with(RuleResult::Breached, $severity);
        }

        return $measured->with(RuleResult::NotBreached);
    }

    /** The current period is judged first, then the comparison period (absent beats incomplete per week). */
    private function incompleteReason(WeekState $week): ?InsufficientReason
    {
        return match ($week->completeness) {
            WeekCompleteness::Absent => InsufficientReason::PeriodAbsent,
            WeekCompleteness::Incomplete => InsufficientReason::IncompletePeriod,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function period(WeekState $week, WeekTotals $totals): array
    {
        return [
            'start' => $week->weekStart->format('Y-m-d'),
            'end' => $week->weekEnd->format('Y-m-d'),
            'status' => $week->status->value,
            'completeness' => $week->completeness?->value,
            'revenue' => $totals->revenue,
            'target' => $totals->target,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverage(WeekState $latest, WeekState $preceding, EvaluationContext $context): array
    {
        $week = fn (WeekState $state) => [
            'expectedDueBranchDays' => $state->dueBranchDays,
            'expectedBranchDays' => $state->expectedBranchDays,
            'presentBranchDays' => $state->presentBranchDays,
            'coveragePct' => $state->coveragePct,
            'missingDueBranchDays' => array_map(fn ($gap) => [
                'branch_code' => $gap->branchCode,
                'date' => $gap->date->format('Y-m-d'),
                'kind' => $gap->kind,
            ], $state->gaps),
        ];

        return [
            'expectedBranches' => $context->expectedBranches,
            'current' => $week($latest),
            'comparison' => $week($preceding),
        ];
    }
}
