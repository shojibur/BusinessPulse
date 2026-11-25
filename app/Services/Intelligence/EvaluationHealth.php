<?php

namespace App\Services\Intelligence;

use App\Models\Department;
use App\Services\Imports\ReportingCoverage;
use Illuminate\Support\Facades\DB;

/**
 * Whether the stored evaluations of a department can be trusted right now, shown by the dashboard, the issue pages
 * and the brief. Written by EvaluateRulesJob, read here. Staleness compares what the evaluation saw (a fingerprint of
 * the department's sales records and the last due date) with what exists now, so it does not depend on import ids or
 * on the clock of the evaluation itself.
 *
 * Statuses: never (no run yet), current, partial (some active rule failed to evaluate in the last run), failed
 * (every rule failed), stale (data or the reporting calendar moved on since the last run).
 */
final class EvaluationHealth
{
    public const NEVER = 'never';

    public const CURRENT = 'current';

    public const PARTIAL = 'partial';

    public const FAILED = 'failed';

    public const STALE = 'stale';

    public function __construct(private readonly ReportingCoverage $coverage) {}

    /** Changes whenever an import adds or supersedes a record of the department. */
    public static function fingerprint(int $departmentId): string
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
     * @return array{status: string, last_evaluated_at: string|null, failed_rules: int}
     */
    public function forDepartment(Department $department): array
    {
        $state = $department->fresh()?->settings['intelligence'] ?? [];
        $lastEvaluatedAt = $state['last_evaluated_at'] ?? null;
        $runStatus = $state['last_run_status'] ?? null;
        $failedRules = (int) ($state['last_run_failed_rules'] ?? 0);

        if ($runStatus === null && $lastEvaluatedAt === null) {
            return ['status' => self::NEVER, 'last_evaluated_at' => null, 'failed_rules' => 0];
        }

        $status = match (true) {
            $runStatus === 'failed' => self::FAILED,
            $this->isStale($department, $state) => self::STALE,
            $runStatus === 'partial' => self::PARTIAL,
            default => self::CURRENT,
        };

        return ['status' => $status, 'last_evaluated_at' => $lastEvaluatedAt, 'failed_rules' => $failedRules];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function isStale(Department $department, array $state): bool
    {
        $fingerprint = $state['evaluated_fingerprint'] ?? null;
        $lastDue = $state['evaluated_last_due_date'] ?? null;

        if ($fingerprint === null || $lastDue === null) {
            return true;
        }

        return $fingerprint !== self::fingerprint($department->id)
            || $lastDue !== $this->coverage->lastDueDate($department)->format('Y-m-d');
    }
}
