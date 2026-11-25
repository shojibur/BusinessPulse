<?php

namespace App\Services\Dashboard;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\WeekCompleteness;
use App\Models\Brief;
use App\Models\Company;
use App\Models\Department;
use App\Models\Issue;
use App\Services\AI\BriefSummaryComposer;
use App\Services\Imports\Freshness;
use App\Services\Imports\ReportingCoverage;
use App\Services\Imports\WeekState;
use App\Services\Intelligence\EvaluationHealth;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;
use Carbon\CarbonImmutable;

/**
 * Assembles the dashboard from S03 coverage and S04 metrics. It adds no arithmetic: every figure is computed by
 * MetricCalculator over Active_Records of the latest closed week (the same week S04 evaluated), never an open week.
 * A missing or incomplete period is reported as insufficient data, never as zero. S07 extends this class with task
 * figures; there are none here.
 */
final class DashboardMetrics
{
    private const SERIES_WEEKS = 8;

    public function __construct(
        private readonly MetricCalculator $metrics,
        private readonly ReportingCoverage $coverage,
        private readonly EvaluationHealth $health,
    ) {}

    public function forOwnerOrManager(Company $company, Department $department): DashboardData
    {
        $latest = $this->coverage->latestClosedWeek($department);
        $previous = $this->coverage->weekState($department, $latest->weekStart->subWeek());
        $freshness = $this->coverage->freshness($department);
        $health = $this->health->forDepartment($department);
        $timezone = $company->timezone;
        $hasData = $freshness->latestSalesDate !== null;

        [$revenue, $change, $target] = $hasData
            ? $this->figures($company, $department, $latest, $previous)
            : [$this->insufficient('period_absent', $latest), $this->insufficient('period_absent', $latest), $this->insufficient('period_absent', $latest)];

        return new DashboardData(
            departmentName: $department->name,
            revenue: $revenue,
            change: $change,
            targetAchievement: $target,
            freshness: $this->freshnessData($freshness, $health, $timezone),
            evaluation: $health,
            coverageNotice: $hasData && $latest->completeness === WeekCompleteness::Incomplete
                ? count($latest->gaps).' branch-days are missing from the latest closed week ('.BriefSummaryComposer::range($latest->weekStart, $latest->weekEnd).').'
                : null,
            openIssueCount: $this->issueCount($company, $department, null),
            highPriorityIssueCount: $this->issueCount($company, $department, [IssueSeverity::High->value, IssueSeverity::Critical->value]),
            revenueVsTarget: $hasData ? $this->series($company, $department, $latest) : [],
            latestBrief: $this->latestBrief($company, $timezone),
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function figures(Company $company, Department $department, WeekState $latest, WeekState $previous): array
    {
        if ($latest->completeness === null || $latest->completeness === WeekCompleteness::Absent) {
            $insufficient = $this->insufficient('period_absent', $latest);

            return [$insufficient, $insufficient, $insufficient];
        }

        if ($latest->completeness === WeekCompleteness::Incomplete) {
            $insufficient = $this->insufficient('incomplete_period', $latest);

            return [$insufficient, $insufficient, $insufficient];
        }

        $branches = $this->coverage->expectedBranches($department);
        $totals = $this->metrics->weekTotals($department->id, $latest->weekStart, $company->trading_days, $branches);
        $period = $this->period($latest);

        $revenue = ['available' => true, 'value' => $totals->revenue, 'display' => $this->metrics->formatMoney($totals->revenue)] + $period;

        $targetPct = $this->metrics->targetAchievementPct($totals->revenue, $totals->target);
        $target = $targetPct === Indeterminate::ZeroDenominator
            ? $this->insufficient('zero_denominator', $latest)
            : ['available' => true, 'value' => $this->metrics->display2($targetPct), 'display' => $this->metrics->display2($targetPct).'%',
                'target' => $this->metrics->formatMoney($totals->target)] + $period;

        if ($previous->completeness !== WeekCompleteness::Complete) {
            $change = $this->insufficient('comparison_week_incomplete', $previous);
        } else {
            $before = $this->metrics->weekTotals($department->id, $previous->weekStart, $company->trading_days, $branches);
            $pct = $this->metrics->weeklyChangePct($totals->revenue, $before->revenue);
            $change = $pct === Indeterminate::ZeroDenominator
                ? $this->insufficient('zero_denominator', $latest)
                : ['available' => true, 'value' => $this->metrics->display2($pct), 'display' => $this->metrics->display2($pct).'%',
                    'comparison' => $this->metrics->formatMoney($before->revenue),
                    'comparison_period_start' => $previous->weekStart->format('Y-m-d'), 'comparison_period_end' => $previous->weekEnd->format('Y-m-d')] + $period;
        }

        return [$revenue, $change, $target];
    }

    /**
     * @return array<string, mixed>
     */
    private function insufficient(string $reason, WeekState $week): array
    {
        $range = BriefSummaryComposer::range($week->weekStart, $week->weekEnd);

        return ['available' => false, 'reason' => $reason, 'message' => match ($reason) {
            'incomplete_period' => "The latest closed week ({$range}) is missing sales data, so its figures are not shown.",
            'period_absent' => 'No sales data for the latest closed week yet.',
            'comparison_week_incomplete' => "The week before ({$range}) is incomplete, so the weekly change cannot be compared.",
            default => 'This cannot be calculated because the comparison value is zero.',
        }] + $this->period($week);
    }

    /**
     * @return array<string, string>
     */
    private function period(WeekState $week): array
    {
        return ['period_start' => $week->weekStart->format('Y-m-d'), 'period_end' => $week->weekEnd->format('Y-m-d')];
    }

    /**
     * @param  array{status: string, last_evaluated_at: string|null, failed_rules: int}  $health
     * @return array<string, mixed>
     */
    private function freshnessData(Freshness $freshness, array $health, string $timezone): array
    {
        $behind = $freshness->state->value === 'behind';
        $evaluatedAt = $health['last_evaluated_at'] === null ? null
            : CarbonImmutable::parse($health['last_evaluated_at'])->setTimezone($timezone)->format('j M Y H:i');

        $message = null;
        if ($freshness->latestSalesDate === null) {
            $message = 'No sales data yet.';
        } elseif ($behind) {
            $message = 'Sales for '.BriefSummaryComposer::range($freshness->overdueFrom, $freshness->overdueTo, false)
                .' not yet received: '.$freshness->overdueBranchDays.' branch-days overdue. '
                .($evaluatedAt === null ? 'No evaluation has run yet.' : "Intelligence has not been refreshed since {$evaluatedAt}.");
        }

        return [
            'state' => $freshness->latestSalesDate === null ? 'no_data' : $freshness->state->value,
            'behind' => $behind || $freshness->latestSalesDate === null,
            'message' => $message,
            'latest_sales_date' => $freshness->latestSalesDate?->format('Y-m-d'),
            'overdue_branch_days' => $freshness->overdueBranchDays,
        ];
    }

    /**
     * @param  list<string>|null  $severities
     */
    private function issueCount(Company $company, Department $department, ?array $severities): int
    {
        return Issue::query()
            ->where('department_id', $department->id)
            ->where('status', '!=', IssueStatus::Resolved->value)
            ->when($severities !== null, fn ($query) => $query->whereIn('severity', $severities))
            ->count();
    }

    /**
     * The last closed weeks that hold records; incomplete weeks are gaps (never zero).
     *
     * @return list<array<string, mixed>>
     */
    private function series(Company $company, Department $department, WeekState $latest): array
    {
        $states = $this->coverage->weekStates($department, $latest->weekStart->subWeeks(self::SERIES_WEEKS - 1), $latest->weekStart);
        $branches = $this->coverage->expectedBranches($department);
        $points = [];

        foreach ($states as $week) {
            if ($week->completeness === null || $week->completeness === WeekCompleteness::Absent) {
                continue;
            }

            $complete = $week->completeness === WeekCompleteness::Complete;
            $totals = $complete ? $this->metrics->weekTotals($department->id, $week->weekStart, $company->trading_days, $branches) : null;

            $points[] = [
                'week_start' => $week->weekStart->format('Y-m-d'),
                'week_end' => $week->weekEnd->format('Y-m-d'),
                'label' => BriefSummaryComposer::range($week->weekStart, $week->weekEnd, false),
                'revenue' => $totals?->revenue,
                'target' => $totals?->target,
                'is_complete' => $complete,
            ];
        }

        return $points;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestBrief(Company $company, string $timezone): ?array
    {
        $brief = Brief::query()->where('company_id', $company->id)->completed()->latest('generated_at')->latest('id')->first();

        return $brief === null ? null : [
            'id' => $brief->id,
            'headline' => $brief->headline,
            'source' => $brief->source?->value,
            'coverage_status' => $brief->coverage_status?->value,
            'generated_at' => $brief->generated_at?->setTimezone($timezone)->toIso8601String(),
        ];
    }
}
