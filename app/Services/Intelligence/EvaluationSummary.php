<?php

namespace App\Services\Intelligence;

use App\Models\RuleEvaluation;

/**
 * A flat, serializable summary of one stored evaluation (strings and integers only).
 */
final readonly class EvaluationSummary
{
    public function __construct(
        public int $evaluationId,
        public string $result,
        public ?string $severity,
        public string $metricName,
        public string $periodStart,
        public string $periodEnd,
        public ?string $comparisonStart,
        public ?string $comparisonEnd,
        public ?string $currentValue,
        public ?string $comparisonValue,
        public ?string $differenceValue,
        public ?string $differencePct,
        public ?string $thresholdUsed,
        public ?string $asOfDate,
        public string $lastDueDate,
        public string $evaluatedAt,
    ) {}

    public static function fromEvaluation(RuleEvaluation $evaluation): self
    {
        return new self(
            $evaluation->id,
            $evaluation->result->value,
            $evaluation->severity?->value,
            $evaluation->metric_name,
            $evaluation->period_start->format('Y-m-d'),
            $evaluation->period_end->format('Y-m-d'),
            $evaluation->comparison_start?->format('Y-m-d'),
            $evaluation->comparison_end?->format('Y-m-d'),
            $evaluation->current_value,
            $evaluation->comparison_value,
            $evaluation->difference_value,
            $evaluation->difference_pct,
            $evaluation->evidence['thresholdUsed'] ?? null,
            $evaluation->as_of_date?->format('Y-m-d'),
            $evaluation->last_due_date->format('Y-m-d'),
            $evaluation->evaluated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, int|string|null>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['evaluationId'], $data['result'], $data['severity'], $data['metricName'],
            $data['periodStart'], $data['periodEnd'], $data['comparisonStart'], $data['comparisonEnd'],
            $data['currentValue'], $data['comparisonValue'], $data['differenceValue'], $data['differencePct'],
            $data['thresholdUsed'], $data['asOfDate'], $data['lastDueDate'], $data['evaluatedAt'],
        );
    }
}
