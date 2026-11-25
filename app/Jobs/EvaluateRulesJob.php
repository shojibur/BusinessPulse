<?php

namespace App\Jobs;

use App\Actions\Intelligence\SeedDefaultRules;
use App\Enums\RuleResult;
use App\Events\Intelligence\EvaluationRunCompleted;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Models\RuleEvaluation;
use App\Services\Imports\ReportingCoverage;
use App\Services\Intelligence\EvaluationContext;
use App\Services\Intelligence\EvaluationOutcome;
use App\Services\Intelligence\Evaluators\MissingSalesDataEvaluator;
use App\Services\Intelligence\Evaluators\RuleEvaluator;
use App\Services\Intelligence\Evaluators\SalesTargetShortfallEvaluator;
use App\Services\Intelligence\Evaluators\WeeklyRevenueDeclineEvaluator;
use App\Services\Intelligence\EvidenceBuilder;
use App\Services\Intelligence\IssueEngine;
use App\Services\Intelligence\RunKey;
use App\Support\BusinessClock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Evaluates every active rule of ONE department against the reporting calendar and stores each evaluation with
 * its evidence; breached results then create or re-detect issues. Idempotent: the same inputs reuse the stored
 * evaluation. Correctness never depends on the locks (the unique indexes decide); the locks only avoid wasted
 * work and, together with a data fingerprint check, keep one run's evidence from mixing two states of the data
 * while an import commits.
 *
 * Queue `default`; one pending job per department (unique until processing starts). `tries` is high but only
 * `maxExceptions` real failures count: waiting for a lock must not exhaust the retries.
 */
class EvaluateRulesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 120;

    // Releases (lock waits) do not count as failures; only 3 real exceptions fail the job.
    public int $tries = 20;

    public int $maxExceptions = 3;

    public int $uniqueFor = 900;

    private const STABLE_ATTEMPTS = 3;

    /** Seconds to wait for a department lock before releasing the job back to the queue. */
    public int $lockWaitSeconds = 30;

    public function __construct(
        public readonly int $departmentId,
        public readonly ?int $importId = null,
        public readonly ?int $actorUserId = null,
    ) {
        $this->onQueue('default');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->departmentId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SeedDefaultRules $seed, ReportingCoverage $coverage, BusinessClock $clock): void
    {
        $evaluateLock = Cache::lock("evaluate:{$this->departmentId}", 120);

        try {
            $evaluateLock->block($this->lockWaitSeconds);
        } catch (LockTimeoutException) {
            $this->release(15);

            return;
        }

        try {
            // Also wait for a running import write of this department, so its commit cannot land mid-evaluation.
            $importLock = Cache::lock("import-processing:department:{$this->departmentId}", 150);

            try {
                $importLock->block($this->lockWaitSeconds);
            } catch (LockTimeoutException) {
                $this->release(15);

                return;
            }

            try {
                $this->run($seed, $coverage, $clock);
            } finally {
                $importLock->release();
            }
        } finally {
            $evaluateLock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        // Ids and the exception class only: no sales values, paths or session data.
        Log::error('evaluation_job_failed', ['department_id' => $this->departmentId, 'exception' => $exception::class]);
    }

    private function run(SeedDefaultRules $seed, ReportingCoverage $coverage, BusinessClock $clock): void
    {
        $department = Department::query()->with('company')->find($this->departmentId);

        if ($department === null) {
            return;
        }

        $seed->ensureRulesExist($department->id);

        $rules = BusinessRule::query()->where('department_id', $department->id)->where('is_active', true)->orderBy('id')->get();
        $evaluators = $this->evaluators();

        // Captured BEFORE reading: an import that completes during the run still makes the result stale.
        $throughImportId = $this->latestCompletedImportId($department->id);

        [$context, $outcomes] = $this->evaluateStable($department, $rules, $evaluators, $coverage, $clock);

        if ($context === null) {
            // The data kept changing under the run: do not store a mixed snapshot, try again shortly.
            Log::warning('evaluation_data_unstable', ['department_id' => $department->id]);
            $this->release(15);

            return;
        }

        $evaluationIds = [];
        foreach ($rules as $rule) {
            if (! isset($outcomes[$rule->id])) {
                continue; // inactive or failed to evaluate
            }

            try {
                $evaluationIds[] = $this->persist($rule, $context, $outcomes[$rule->id]);
            } catch (Throwable $e) {
                Log::warning('rule_evaluation_failed', ['rule_id' => $rule->id, 'department_id' => $department->id, 'exception' => $e::class]);
            }
        }

        if ($evaluationIds === []) {
            return; // every rule failed: no last-evaluated update and no run event
        }

        $this->touchLastEvaluated($department->id, $context, $throughImportId);
        $this->announceRunCompleted($department->id, $evaluationIds);
    }

    /**
     * Evaluate every rule (pure reads) and accept the result only when no sales record changed meanwhile.
     *
     * @param  Collection<int, BusinessRule>  $rules
     * @param  array<string, RuleEvaluator>  $evaluators
     * @return array{0: EvaluationContext|null, 1: array<int, EvaluationOutcome>}
     */
    private function evaluateStable(Department $department, $rules, array $evaluators, ReportingCoverage $coverage, BusinessClock $clock): array
    {
        for ($attempt = 1; $attempt <= self::STABLE_ATTEMPTS; $attempt++) {
            $before = $this->fingerprint($department->id);
            $context = EvaluationContext::build($department, $coverage, $clock);
            $outcomes = [];

            foreach ($rules as $rule) {
                try {
                    $outcomes[$rule->id] = $evaluators[$rule->key->value]->evaluate($rule, $department, $context);
                } catch (Throwable $e) {
                    Log::warning('rule_evaluation_failed', ['rule_id' => $rule->id, 'department_id' => $department->id, 'exception' => $e::class]);
                }
            }

            if ($this->fingerprint($department->id) === $before) {
                return [$context, $outcomes];
            }
        }

        return [null, []];
    }

    /**
     * Changes whenever an import adds or supersedes a record of the department.
     */
    private function fingerprint(int $departmentId): string
    {
        $row = DB::selectOne(
            "select count(*) as total, count(*) filter (where is_active) as active, coalesce(max(id), 0) as last_id,
                    coalesce(max(updated_at)::text, '') as touched
               from sales_records where department_id = ?",
            [$departmentId],
        );

        return implode('|', [$row->total, $row->active, $row->last_id, $row->touched]);
    }

    /**
     * Store the evaluation (insert or reuse by run key) and, for a new breached one, update the issue, in one
     * transaction. Returns the evaluation id.
     */
    private function persist(BusinessRule $rule, EvaluationContext $context, EvaluationOutcome $outcome): int
    {
        return DB::transaction(function () use ($rule, $context, $outcome): int {
            $runKey = app(RunKey::class)->compute($rule, $context, $outcome);
            $evidence = app(EvidenceBuilder::class)->build($rule, $context, $outcome);
            $now = now()->format('Y-m-d H:i:s');

            $inserted = DB::table('rule_evaluations')->insertOrIgnore([
                'business_rule_id' => $rule->id,
                'department_id' => $rule->department_id,
                'import_id' => $this->importId,
                'period_start' => $outcome->periodStart->format('Y-m-d'),
                'period_end' => $outcome->periodEnd->format('Y-m-d'),
                'comparison_start' => $outcome->comparisonStart?->format('Y-m-d'),
                'comparison_end' => $outcome->comparisonEnd?->format('Y-m-d'),
                'metric_name' => $outcome->metricName,
                'current_value' => $outcome->currentValue,
                'comparison_value' => $outcome->comparisonValue,
                'difference_value' => $outcome->differenceValue,
                'difference_pct' => $outcome->differencePct,
                'result' => $outcome->result->value,
                'severity' => $outcome->severity?->value,
                'insufficient_reason' => $outcome->reason?->value,
                'as_of_date' => $context->asOf?->format('Y-m-d'),
                'last_due_date' => $context->lastDue->format('Y-m-d'),
                'evidence' => json_encode($evidence, JSON_UNESCAPED_SLASHES),
                'evaluated_at' => $context->now->utc()->format('Y-m-d H:i:s'),
                'run_key' => $runKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $evaluation = RuleEvaluation::query()
                ->where('business_rule_id', $rule->id)
                ->where('period_start', $outcome->periodStart->format('Y-m-d'))
                ->where('period_end', $outcome->periodEnd->format('Y-m-d'))
                ->where('run_key', $runKey)
                ->firstOrFail();

            if ($outcome->result === RuleResult::Breached) {
                app(IssueEngine::class)->applyBreach($rule, $evaluation, reused: $inserted === 0);
            }

            return $evaluation->id;
        });
    }

    /** The newest completed import of the department (0 when none): the staleness marker of an evaluation. */
    private function latestCompletedImportId(int $departmentId): int
    {
        return (int) DB::table('imports')
            ->where('department_id', $departmentId)
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->max('id');
    }

    /** Atomic: concurrent writers of other settings keys are never overwritten. */
    private function touchLastEvaluated(int $departmentId, EvaluationContext $context, int $throughImportId): void
    {
        DB::update(
            "update departments
                set settings = jsonb_set(
                    coalesce(settings, '{}'::jsonb),
                    '{intelligence}',
                    coalesce(settings->'intelligence', '{}'::jsonb)
                        || jsonb_build_object('last_evaluated_at', ?::text, 'evaluated_through_import_id', ?::int),
                    true)
              where id = ?",
            [$context->now->toIso8601String(), $throughImportId, $departmentId],
        );
    }

    /**
     * @param  list<int>  $evaluationIds
     */
    private function announceRunCompleted(int $departmentId, array $evaluationIds): void
    {
        $evaluationIds = array_values(array_unique($evaluationIds));
        sort($evaluationIds);

        try {
            EvaluationRunCompleted::dispatch($departmentId, $this->importId, $this->actorUserId, $evaluationIds);
        } catch (Throwable $e) {
            // A failed announcement never fails the job or undoes the committed evaluations.
            Log::warning('evaluation_run_completed_dispatch_failed', ['department_id' => $departmentId, 'exception' => $e::class]);
        }
    }

    /**
     * @return array<string, RuleEvaluator>
     */
    private function evaluators(): array
    {
        return [
            'weekly_revenue_decline' => app(WeeklyRevenueDeclineEvaluator::class),
            'sales_target_shortfall' => app(SalesTargetShortfallEvaluator::class),
            'missing_sales_data' => app(MissingSalesDataEvaluator::class),
        ];
    }
}
