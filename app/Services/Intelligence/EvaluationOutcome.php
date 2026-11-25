<?php

namespace App\Services\Intelligence;

use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleResult;
use Carbon\CarbonImmutable;

/**
 * The pure result of evaluating one rule (nothing is written yet). Money values are 2-decimal strings,
 * differencePct is a 4-decimal string.
 */
final readonly class EvaluationOutcome
{
    /**
     * @param  array<string, mixed>  $periods  evidence of the evaluated periods
     * @param  array<string, mixed>  $coverage  evidence of branch coverage
     * @param  list<int>  $recordIds  active record ids in scope (run key and evidence)
     * @param  list<int>  $importIds  imports of those records
     */
    public function __construct(
        public RuleResult $result,
        public ?IssueSeverity $severity,
        public ?InsufficientReason $reason,
        public string $metricName,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public ?CarbonImmutable $comparisonStart,
        public ?CarbonImmutable $comparisonEnd,
        public ?string $currentValue,
        public ?string $comparisonValue,
        public ?string $differenceValue,
        public ?string $differencePct,
        public array $periods,
        public array $coverage,
        public array $recordIds,
        public array $importIds,
    ) {}

    /** A copy with a different decision (used by the evaluators after the common data is gathered). */
    public function with(RuleResult $result, ?IssueSeverity $severity = null, ?InsufficientReason $reason = null): self
    {
        return new self(
            $result, $severity, $reason, $this->metricName, $this->periodStart, $this->periodEnd,
            $this->comparisonStart, $this->comparisonEnd, $this->currentValue, $this->comparisonValue,
            $this->differenceValue, $this->differencePct, $this->periods, $this->coverage, $this->recordIds, $this->importIds,
        );
    }
}
