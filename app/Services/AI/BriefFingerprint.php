<?php

namespace App\Services\AI;

use App\Enums\IssueStatus;
use App\Models\Department;
use App\Models\Issue;
use App\Models\User;
use App\Services\Imports\ReportingCoverage;
use App\Services\Intelligence\EvaluationHealth;
use App\Support\BusinessClock;

/**
 * A short hash of everything a brief is written from: its scope, today's business date, the reporting week and the
 * sales freshness, the evaluation health, every open issue with its latest evaluation, and which AI is configured.
 * Two requests with the same fingerprint would produce the same brief, so the second reuses the first.
 */
final class BriefFingerprint
{
    public function __construct(
        private readonly ReportingCoverage $coverage,
        private readonly EvaluationHealth $health,
        private readonly BusinessClock $clock,
        private readonly AiSettings $ai,
    ) {}

    public function for(User $user): string
    {
        $company = $user->company;
        $manager = $user->isManager() && $user->department_id !== null;
        $department = $manager
            ? Department::query()->where('company_id', $company->id)->findOrFail($user->department_id)
            : ($company->salesDepartment() ?? throw new \RuntimeException('Company has no department.'));
        $departmentIds = $manager ? [$department->id] : $company->departments()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $issues = Issue::query()->whereIn('department_id', $departmentIds)->where('status', '!=', IssueStatus::Resolved->value)
            ->orderBy('id')->get(['id', 'status', 'severity', 'last_detected_at', 'latest_evaluation_id'])
            ->map(fn (Issue $issue) => [$issue->id, $issue->status->value, $issue->severity->value, $issue->last_detected_at?->getTimestamp(), $issue->latest_evaluation_id])->all();

        $week = $this->coverage->latestClosedWeek($department);
        $freshness = $this->coverage->freshness($department);
        $health = $this->health->forDepartment($department);
        $text = $this->ai->text();

        return hash('sha256', json_encode([
            'scope' => $manager ? $department->id : 'company',
            'date' => $this->clock->today($company->timezone)->format('Y-m-d'),
            'week' => [$week->weekStart->format('Y-m-d'), $week->completeness?->value],
            'sales' => $freshness->latestSalesDate?->format('Y-m-d'),
            'evaluation' => [$health['status'], $health['last_evaluated_at']],
            'issues' => $issues,
            // A different AI (or none) writes different wording, so it is a different brief.
            'ai' => [$text->configured(), $text->provider, $text->model],
        ], JSON_THROW_ON_ERROR));
    }
}
