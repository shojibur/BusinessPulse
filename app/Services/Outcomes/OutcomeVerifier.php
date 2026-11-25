<?php

namespace App\Services\Outcomes;

use App\Enums\IssueStatus;
use App\Enums\OutcomeMetric;
use App\Enums\OutcomePhase;
use App\Enums\OutcomeResult;
use App\Events\Outcomes\OutcomeEvaluated;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\IssueTransitionNotAllowedException;
use App\Services\Tasks\IssueLifecycleBridge;
use App\Services\Tasks\IssueTaskCompletion;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deterministic outcome verification. For every issue in `action_in_progress` or `monitoring` it compares the preserved
 * baseline evaluation with the latest follow-up evaluation of the same rule, classifies the follow-up period (pre-action,
 * during-action or post-action), measures it with the metric of the rule, stores an immutable outcome and, ONLY for a
 * comparable post-action period of a monitoring issue, asks IssueLifecycle to resolve or reopen. AI is never involved, task
 * completion never resolves anything on its own, and nothing here claims that actions caused the change.
 */
class OutcomeVerifier
{
    public function __construct(
        private readonly OutcomePhaseResolver $phases,
        private readonly OutcomeMetricCalculator $calculator,
        private readonly ComparabilityAssessor $assessor,
        private readonly OutcomeEvidenceBuilder $evidence,
        private readonly IssueLifecycle $lifecycle,
        private readonly IssueLifecycleBridge $bridge,
        private readonly IssueTaskCompletion $tasks,
        private readonly BusinessClock $clock,
    ) {}

    /** @throws VerificationIncomplete when at least one issue failed (the others were still verified) */
    public function verifyDepartment(int $departmentId): void
    {
        $ids = Issue::query()->where('department_id', $departmentId)
            ->whereIn('status', [IssueStatus::ActionInProgress->value, IssueStatus::Monitoring->value])->orderBy('id')->pluck('id');

        $failures = 0;

        foreach ($ids as $id) {
            try {
                $this->verifyIssue((int) $id);
            } catch (Throwable $e) {
                $failures++;
                Log::warning('verify_issue_failed', ['issue_id' => $id, 'exception' => $e::class]);
            }
        }

        if ($failures > 0) {
            throw new VerificationIncomplete($failures);
        }
    }

    public function verifyIssue(int $issueId): ?OutcomeEvaluated
    {
        return DB::transaction(function () use ($issueId): ?OutcomeEvaluated {
            $issue = Issue::query()->whereKey($issueId)->lockForUpdate()->first();

            if ($issue === null || ! in_array($issue->status, [IssueStatus::ActionInProgress, IssueStatus::Monitoring], true)) {
                return null;
            }

            // Recovery path for S07: a monitoring issue with an open task goes back to action BEFORE anything is judged.
            if ($this->bridge->reconcile($issue->id)) {
                $issue->refresh();

                if (! in_array($issue->status, [IssueStatus::ActionInProgress, IssueStatus::Monitoring], true)) {
                    return null;
                }
            }

            $baseline = RuleEvaluation::query()->find($issue->baseline_evaluation_id); // never latest_evaluation_id
            $followup = RuleEvaluation::query()->where('business_rule_id', $issue->business_rule_id)->where('department_id', $issue->department_id)
                ->where('id', '!=', $baseline->id)->orderByDesc('period_start')->orderByDesc('id')->first();

            if ($followup === null) {
                return null;
            }

            $exists = DB::table('outcomes')->where('issue_id', $issue->id)->where('followup_evaluation_id', $followup->id)->exists();

            if ($exists) {
                return null; // the unique constraint below is the real guard
            }

            $issue->loadMissing('department.company');
            $department = $issue->department;
            $timezone = $department->company->timezone;
            $rule = BusinessRule::query()->findOrFail($issue->business_rule_id);
            $metric = OutcomeMetric::forRuleKey($rule->key);

            $phase = $this->phases->resolve(
                $issue->action_started_at === null ? null : CarbonImmutable::instance($issue->action_started_at),
                $issue->monitoring_started_at === null ? null : CarbonImmutable::instance($issue->monitoring_started_at),
                CarbonImmutable::parse($followup->period_start->format('Y-m-d')), CarbonImmutable::parse($followup->period_end->format('Y-m-d')), $timezone,
            );
            $assessment = $this->assessor->assess($department, $metric, $baseline, $followup);

            $figures = OutcomeFigures::empty();
            $condition = null;
            $resolutionMet = false;

            if ($assessment->comparable()) {
                $figures = $this->calculator->compute($metric, $baseline, $followup);
                $condition = $this->calculator->condition($metric, $baseline, $followup, $figures);
                $resolutionMet = $phase === OutcomePhase::PostAction && $condition['holds'];
            }

            $result = $assessment->comparable() ? $figures->result : OutcomeResult::NotEvaluable;
            $evidence = $this->evidence->build($rule, $issue, $baseline, $followup, $phase, $assessment, $metric, $figures, $condition, $resolutionMet, $timezone);

            $outcomeId = $this->insert($issue, $baseline, $followup, $phase, $metric, $figures, $result, $assessment->failedReason?->value, $assessment->comparable(), $resolutionMet, $evidence);

            if ($outcomeId === null) {
                return null; // a conflicting insert: nothing else happens
            }

            $action = $this->decide($phase, $result, $resolutionMet, $issue->status, $assessment->comparable());
            $lifecycleAction = 'none';

            if ($action === 'resolve') {
                // Re-check at the moment of resolution (under the issue lock): no linked task may be open.
                if ($this->tasks->isSatisfied($issue->id)) {
                    $lifecycleAction = $this->callLifecycle(fn () => $this->lifecycle->resolveVerified($issue, 'outcome:'.$outcomeId), 'resolved', $issue->id);
                } else {
                    $this->bridge->reconcile($issue->id);
                }
            } elseif ($action === 'reopen') {
                $lifecycleAction = $this->callLifecycle(fn () => $this->lifecycle->reopen($issue, "Outcome {$outcomeId} showed the metric worsened"), 'reopened', $issue->id);
            }

            $event = new OutcomeEvaluated(
                $outcomeId, $issue->id, $issue->department_id, $department->company_id, null, $this->clock->nowUtc(),
                $result->value, $phase->value, $assessment->comparable(), $resolutionMet, $lifecycleAction,
            );
            event($event); // after-commit event: held until the outer transaction commits

            return $event;
        });
    }

    /** The only gate to the lifecycle: only a post-action period of a monitoring issue can resolve or reopen. */
    public function decide(OutcomePhase $phase, OutcomeResult $result, bool $resolutionMet, IssueStatus $status, bool $comparable): string
    {
        if ($phase !== OutcomePhase::PostAction || $status !== IssueStatus::Monitoring) {
            return 'none';
        }

        if ($resolutionMet) {
            return 'resolve';
        }

        return $comparable && $result === OutcomeResult::Worsened ? 'reopen' : 'none';
    }

    private function callLifecycle(callable $call, string $okLabel, int $issueId): string
    {
        try {
            DB::transaction($call); // a savepoint: a refusal rolls back only the lifecycle part

            return $okLabel;
        } catch (IssueTransitionNotAllowedException $e) {
            Log::warning('outcome_lifecycle_rejected', ['issue_id' => $issueId, 'exception' => $e::class]);

            return 'none';
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function insert(Issue $issue, RuleEvaluation $baseline, RuleEvaluation $followup, OutcomePhase $phase, OutcomeMetric $metric, OutcomeFigures $f, OutcomeResult $result, ?string $reason, bool $comparable, bool $met, array $evidence): ?int
    {
        $row = DB::selectOne(
            'INSERT INTO outcomes (issue_id, baseline_evaluation_id, followup_evaluation_id, phase, outcome_metric, baseline_value, followup_value, reference_value,
                                   absolute_change, relative_change_pct, point_change, result, not_evaluable_reason, comparable, resolution_condition_met, evidence, evaluated_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, now(), now())
             ON CONFLICT (issue_id, followup_evaluation_id) DO NOTHING
             RETURNING id',
            [
                $issue->id, $baseline->id, $followup->id, $phase->value, $metric->value,
                $f->baseline, $f->followup, $f->reference, $f->absoluteChange, $f->relativeChangePct, $f->pointChange,
                $result->value, $reason, $comparable ? 'true' : 'false', $met ? 'true' : 'false',
                json_encode($evidence, JSON_UNESCAPED_SLASHES), $this->clock->nowUtc()->format('Y-m-d H:i:s'),
            ],
        );

        return $row === null ? null : (int) $row->id;
    }
}
