<?php

namespace App\Http\Controllers;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\RuleKey;
use App\Models\Department;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Services\Intelligence\EvaluationHealth;
use App\Services\Intelligence\MetricCalculator;
use App\Services\Meetings\MeetingsForIssue;
use App\Services\Outcomes\IssueOutcomeProps;
use App\Services\Tasks\TasksForIssue;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Issue list and detail. `show` composes ALL of its props itself; later specs (S05 briefs, S06 meetings, S07 tasks,
 * S08 outcomes) append their own props here and add their own Vue panel components to the page. Every figure shown
 * comes from stored evaluations and their evidence, never from a recomputation.
 */
class IssueController extends Controller
{
    public function __construct(private readonly MetricCalculator $metrics, private readonly EvaluationHealth $health) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Issue::class);
        $user = $request->user();

        $status = $request->query('status');
        $status = is_string($status) ? $status : 'open_issues';
        $severity = $request->query('severity');
        $severity = is_string($severity) && IssueSeverity::tryFrom($severity) !== null ? $severity : null;

        $issues = Issue::visibleTo($user)
            ->with(['department:id,name,company_id', 'rule:id,key,name', 'latestEvaluation'])
            ->when($status === 'open_issues', fn ($query) => $query->where('status', '!=', IssueStatus::Resolved->value))
            ->when(IssueStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when($severity !== null, fn ($query) => $query->where('severity', $severity))
            // High first, then the most recently detected.
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByDesc('last_detected_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $department = $this->userDepartment($request);
        $timezone = $department?->company?->timezone ?? config('business.timezone');

        return Inertia::render('issues/Index', [
            'issues' => $issues->getCollection()->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'department' => $issue->department->name,
                'severity' => $issue->severity->value,
                'status' => $issue->status->value,
                'status_label' => $issue->status->label(),
                'first_detected_at' => $this->local($issue->first_detected_at, $timezone),
                'last_detected_at' => $this->local($issue->last_detected_at, $timezone),
                'detection_count' => $issue->detection_count,
                'key_figure' => $this->keyFigure($issue->rule->key, $issue->latestEvaluation),
            ])->values(),
            'pagination' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'total' => $issues->total(),
                'prev_url' => $issues->previousPageUrl(),
                'next_url' => $issues->nextPageUrl(),
            ],
            'filters' => ['status' => $status, 'severity' => $severity],
            'hasAnyIssue' => Issue::visibleTo($user)->exists(),
            'lastEvaluatedAt' => $department?->settings['intelligence']['last_evaluated_at'] ?? null,
            'refreshDepartmentId' => $department?->id,
        ]);
    }

    public function show(Request $request, Issue $issue): Response
    {
        Gate::authorize('view', $issue);

        $issue->load(['department', 'rule', 'baselineEvaluation', 'latestEvaluation', 'recurrenceOf:id,title,status']);
        $timezone = $issue->department->company->timezone;

        // The most recent evaluation of this rule and department, whatever its result: the issue itself is
        // never changed by a later not_breached or insufficient_data evaluation.
        $latestOfRule = RuleEvaluation::query()
            ->where('business_rule_id', $issue->business_rule_id)
            ->where('department_id', $issue->department_id)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first();

        $health = $this->health->forDepartment($issue->department);
        $lastEvaluatedAt = $health['last_evaluated_at'];

        return Inertia::render('issues/Show', [
            'issue' => [
                'id' => $issue->id,
                'title' => $issue->title,
                'severity' => $issue->severity->value,
                'status' => $issue->status->value,
                'status_label' => $issue->status->label(),
                'department' => $issue->department->name,
                'rule_key' => $issue->rule->key->value,
                'rule_name' => $issue->rule->name,
                'first_detected_at' => $this->local($issue->first_detected_at, $timezone),
                'last_detected_at' => $this->local($issue->last_detected_at, $timezone),
                'detection_count' => $issue->detection_count,
                'action_started_at' => $this->local($issue->action_started_at, $timezone),
                'monitoring_started_at' => $this->local($issue->monitoring_started_at, $timezone),
                'resolved_at' => $this->local($issue->resolved_at, $timezone),
                'resolution_type' => $issue->resolution_type?->value,
                'resolution_note' => $issue->resolution_note,
                'recurrence_of' => $issue->recurrenceOf === null ? null : [
                    'id' => $issue->recurrenceOf->id,
                    'title' => $issue->recurrenceOf->title,
                ],
            ],
            'baseline' => $this->evaluation($issue->baselineEvaluation, $timezone),
            'latest' => $this->evaluation($issue->latestEvaluation, $timezone),
            'latestOfRule' => $latestOfRule === null ? null : $this->evaluation($latestOfRule, $timezone),
            'meetings' => app(MeetingsForIssue::class)($issue, $request->user()),
            'tasks' => app(TasksForIssue::class)($issue, $request->user()),
            'outcomes' => fn () => app(IssueOutcomeProps::class)->outcomes($issue, $request->user()),
            'feedback' => fn () => app(IssueOutcomeProps::class)->feedback($issue, $request->user()),
            'trace' => Inertia::defer(fn () => app(IssueOutcomeProps::class)->trace($issue, $request->user())),
            'statusHistory' => collect($issue->status_history)->map(fn (array $entry) => [
                'from' => $entry['from'],
                'to' => $entry['to'],
                'at' => $entry['at'],
                'reason' => $entry['reason'] ?? null,
                'trigger' => $entry['trigger'] ?? null,
                'by_user_id' => $entry['by_user_id'] ?? null,
            ])->values(),
            'actions' => [
                'acknowledge' => $issue->status === IssueStatus::Open,
                'monitoring' => $issue->status === IssueStatus::ActionInProgress,
                'resolve' => $issue->status->isOpen(),
            ],
            'lastEvaluatedAt' => $lastEvaluatedAt,
            'evaluationStatus' => $health['status'],
            'stale' => $health['status'] !== EvaluationHealth::CURRENT,
            'refreshDepartmentId' => $issue->department_id,
        ]);
    }

    /** The department the user refreshes and sees "latest evaluation" for (the Owner's single department). */
    private function userDepartment(Request $request): ?Department
    {
        $user = $request->user();

        return $user->isOwner()
            ? Department::query()->with('company')->where('company_id', $user->company_id)->orderBy('id')->first()
            : Department::query()->with('company')->find($user->department_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluation(RuleEvaluation $evaluation, string $timezone): array
    {
        $evidence = $evaluation->evidence;
        $coverage = $evidence['coverage'] ?? [];
        $pct = $evaluation->difference_pct === null ? null : $this->metrics->display2((string) $evaluation->difference_pct);

        return [
            'id' => $evaluation->id,
            'metric_name' => $evaluation->metric_name,
            'result' => $evaluation->result->value,
            'severity' => $evaluation->severity?->value,
            'insufficient_reason' => $evaluation->insufficient_reason?->value,
            'insufficient_reason_text' => $evaluation->insufficient_reason?->label(),
            'period_start' => $evaluation->period_start->format('Y-m-d'),
            'period_end' => $evaluation->period_end->format('Y-m-d'),
            'comparison_start' => $evaluation->comparison_start?->format('Y-m-d'),
            'comparison_end' => $evaluation->comparison_end?->format('Y-m-d'),
            'current_value' => $evaluation->current_value,
            'comparison_value' => $evaluation->comparison_value,
            'difference_value' => $evaluation->difference_value,
            'difference_pct' => $pct,
            'threshold' => $evidence['thresholdUsed'] ?? null,
            'params' => $evidence['paramsUsed'] ?? [],
            'as_of_date' => $evaluation->as_of_date?->format('Y-m-d'),
            'last_due_date' => $evaluation->last_due_date->format('Y-m-d'),
            'evaluated_at' => $this->local($evaluation->evaluated_at, $timezone),
            'periods' => $evidence['periods'] ?? [],
            'coverage' => [
                'expected_branches' => $coverage['expectedBranches'] ?? [],
                'expected_due_branch_days' => $coverage['expectedDueBranchDays'] ?? ($coverage['current']['expectedDueBranchDays'] ?? null),
                'present_due_branch_days' => $coverage['presentDueBranchDays'] ?? ($coverage['current']['presentBranchDays'] ?? null),
                'missing' => $coverage['missingDueBranchDays'] ?? ($coverage['current']['missingDueBranchDays'] ?? []),
            ],
            'import_ids' => $evidence['contributingImportIds'] ?? [],
        ];
    }

    /**
     * The one headline figure of an issue's latest evaluation, formatted from stored values.
     *
     * @return array<string, string|null>
     */
    private function keyFigure(RuleKey $key, RuleEvaluation $evaluation): array
    {
        $pct = $evaluation->difference_pct === null ? null : $this->metrics->display2((string) $evaluation->difference_pct);

        return match ($key) {
            RuleKey::WeeklyRevenueDecline => ['label' => 'Weekly change', 'value' => $pct === null ? null : $pct.'%'],
            RuleKey::SalesTargetShortfall => ['label' => 'Target achievement', 'value' => $pct === null ? null : $pct.'%'],
            RuleKey::MissingSalesData => [
                'label' => 'Coverage',
                'value' => $evaluation->current_value === null ? null : (int) $evaluation->current_value.' of '.(int) $evaluation->comparison_value.' due branch-days',
            ],
        };
    }

    private function local(?CarbonInterface $time, string $timezone): ?string
    {
        return $time?->setTimezone($timezone)->toIso8601String();
    }
}
