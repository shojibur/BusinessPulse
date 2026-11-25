<?php

namespace App\Services\AI;

use App\Enums\CoverageStatus;
use App\Services\Imports\Freshness;
use App\Services\Imports\WeekState;
use App\Services\Intelligence\EvaluationHealth;

/**
 * Deterministic facts that frame a brief: the latest closed week (S03), freshness (S03) and the evaluation health
 * (S04). Built once per brief from stored data; no arithmetic.
 */
final readonly class BriefContext
{
    /**
     * @param  array{status: string, last_evaluated_at: string|null, failed_rules: int}  $health
     */
    public function __construct(
        public WeekState $week,
        public Freshness $freshness,
        public array $health,
        public string $timezone,
    ) {}

    public function hasSalesData(): bool
    {
        return $this->freshness->latestSalesDate !== null;
    }

    /** An evaluation that failed (fully or partly) means findings may be missing: no normal AI brief. */
    public function evaluationBlocksNormalBrief(): bool
    {
        return in_array($this->health['status'], [EvaluationHealth::FAILED, EvaluationHealth::PARTIAL], true);
    }

    public function coverageStatus(): CoverageStatus
    {
        if ($this->freshness->state->value === 'behind' || ! $this->hasSalesData()
            || in_array($this->health['status'], [EvaluationHealth::STALE, EvaluationHealth::NEVER], true)) {
            return CoverageStatus::Stale;
        }

        if ($this->evaluationBlocksNormalBrief() || in_array($this->week->completeness?->value, ['incomplete', 'absent'], true)) {
            return CoverageStatus::Partial;
        }

        return CoverageStatus::Sufficient;
    }
}
