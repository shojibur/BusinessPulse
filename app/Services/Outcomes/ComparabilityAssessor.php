<?php

namespace App\Services\Outcomes;

use App\Enums\NotEvaluableReason;
use App\Enums\OutcomeMetric;
use App\Enums\RuleResult;
use App\Enums\WeekCompleteness;
use App\Enums\WeekStatus;
use App\Models\Department;
use App\Models\RuleEvaluation;
use App\Services\Imports\ReportingCoverage;
use Carbon\CarbonImmutable;

/**
 * Six ordered checks that decide whether a follow-up evaluation can be compared with the baseline at all. The first failure
 * stops the assessment (later checks are `skipped`): an incomplete period is never compared as if it were complete, and no
 * zero is ever substituted for missing data.
 */
final class ComparabilityAssessor
{
    public function __construct(private readonly ReportingCoverage $coverage) {}

    public function assess(Department $department, OutcomeMetric $metric, RuleEvaluation $baseline, RuleEvaluation $followup): Assessment
    {
        $checks = [];
        $failed = null;
        $week = null;

        $run = function (string $name, callable $test, NotEvaluableReason $reason, bool $applies = true) use (&$checks, &$failed): void {
            if ($failed !== null) {
                $checks[] = ['name' => $name, 'state' => 'skipped'];

                return;
            }

            if (! $applies) {
                $checks[] = ['name' => $name, 'state' => 'not_applicable'];

                return;
            }

            if ($test()) {
                $checks[] = ['name' => $name, 'state' => 'passed'];
            } else {
                $checks[] = ['name' => $name, 'state' => 'failed'];
                $failed = $reason;
            }
        };

        $run('baseline_sufficient', fn () => $baseline->result !== RuleResult::InsufficientData, NotEvaluableReason::BaselineInsufficientData);
        $run('followup_after_baseline', fn () => $followup->period_start->format('Y-m-d') > $baseline->period_end->format('Y-m-d'), NotEvaluableReason::FollowupNotAfterBaseline);
        $run('followup_sufficient', fn () => $followup->result !== RuleResult::InsufficientData, NotEvaluableReason::FollowupInsufficientData);

        $weekly = $metric !== OutcomeMetric::Coverage; // coverage is a rolling window of due days: completeness is the metric itself
        $run('followup_week_complete', function () use ($department, $followup, &$week): bool {
            $state = $this->coverage->weekState($department, CarbonImmutable::parse($followup->period_start->format('Y-m-d'))->startOfWeek());
            $week = [
                'weekStart' => $state->weekStart->format('Y-m-d'), 'weekEnd' => $state->weekEnd->format('Y-m-d'),
                'status' => $state->status->value, 'completeness' => $state->completeness?->value,
                'expectedBranchDays' => $state->expectedBranchDays, 'presentBranchDays' => $state->presentBranchDays,
            ];

            return $state->status === WeekStatus::Closed && $state->completeness === WeekCompleteness::Complete;
        }, NotEvaluableReason::FollowupPeriodPartial, $weekly);

        $run('same_branches', fn () => $this->branches($baseline) === $this->branches($followup), NotEvaluableReason::BranchesChanged);
        $run('baseline_value_nonzero', fn () => bccomp((string) $baseline->current_value, '0', 2) > 0, NotEvaluableReason::ZeroBaselineValue, $metric === OutcomeMetric::WeeklyRevenue);

        return new Assessment($checks, $failed, $week);
    }

    /** @return list<string> */
    private function branches(RuleEvaluation $evaluation): array
    {
        $branches = $evaluation->evidence['coverage']['expectedBranches'] ?? [];
        sort($branches);

        return array_values($branches);
    }
}
