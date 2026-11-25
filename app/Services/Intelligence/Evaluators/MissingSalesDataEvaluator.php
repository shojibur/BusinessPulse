<?php

namespace App\Services\Intelligence\Evaluators;

use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleKey;
use App\Enums\RuleResult;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Services\Imports\CoverageGap;
use App\Services\Intelligence\EvaluationContext;
use App\Services\Intelligence\EvaluationOutcome;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;

/**
 * Coverage of the missing-data window: the Monday of the ISO week before today's week, through the last due
 * date. The denominator is expected branches x DUE trading days of that window, so a day whose cutoff has not
 * passed is never expected and never missing. Any missing due branch-day breaches with medium severity.
 */
final class MissingSalesDataEvaluator implements RuleEvaluator
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    public function key(): RuleKey
    {
        return RuleKey::MissingSalesData;
    }

    public function evaluate(BusinessRule $rule, Department $department, EvaluationContext $context): EvaluationOutcome
    {
        $window = $this->metrics->totalsBetween($department->id, $context->windowStart, $context->windowEnd, $context->calendar['trading_days'], $context->expectedBranches);

        $expected = count($context->expectedBranches) * count($context->dueDates);
        $missing = count($context->gaps);
        $present = $expected - $missing;

        $periods = ['window' => [
            'start' => $context->windowStart->format('Y-m-d'),
            'end' => $context->windowEnd->format('Y-m-d'),
            'dueDates' => array_map(fn ($date) => $date->format('Y-m-d'), $context->dueDates),
            'revenue' => $window->revenue,
            'target' => $window->target,
        ]];
        $coverage = [
            'expectedBranches' => $context->expectedBranches,
            'expectedDueBranchDays' => $expected,
            'presentDueBranchDays' => $present,
            'missingDueBranchDays' => array_map(fn (CoverageGap $gap) => [
                'branch_code' => $gap->branchCode,
                'date' => $gap->date->format('Y-m-d'),
                'kind' => $gap->kind,
            ], $context->gaps),
        ];

        $base = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $this->key()->metricName(),
            $context->windowStart, $context->windowEnd, null, null, null, null, null, null,
            $periods, $coverage, $window->recordIds, $window->importIds,
        );

        if ($context->noRecords) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::NoRecords);
        }

        $coveragePct = $this->metrics->coveragePct($present, $expected);
        if ($coveragePct === Indeterminate::ZeroDenominator) {
            return $base->with(RuleResult::InsufficientData, null, InsufficientReason::ZeroDenominator);
        }

        $measured = new EvaluationOutcome(
            RuleResult::NotBreached, null, null, $base->metricName, $base->periodStart, $base->periodEnd, null, null,
            (string) $present.'.00', (string) $expected.'.00', null, $coveragePct,
            $base->periods, $base->coverage, $base->recordIds, $base->importIds,
        );

        return $missing > 0
            ? $measured->with(RuleResult::Breached, IssueSeverity::Medium)
            : $measured->with(RuleResult::NotBreached);
    }
}
