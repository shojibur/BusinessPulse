<?php

namespace App\Services\Intelligence;

use App\Models\BusinessRule;

/**
 * Identifies the exact inputs of an evaluation. The same unchanged data in the same due state gives the same key
 * (the evaluation is reused); a changed rule, period, calendar, branch list or set of active records gives a new
 * one. `last_due_date` is part of the key on purpose: a rule is judged against the calendar it ran in.
 */
final class RunKey
{
    public function compute(BusinessRule $rule, EvaluationContext $context, EvaluationOutcome $outcome): string
    {
        $recordIds = $outcome->recordIds;
        $importIds = $outcome->importIds;
        sort($recordIds);
        sort($importIds);

        $branches = $context->expectedBranches;
        sort($branches);
        $tradingDays = $context->calendar['trading_days'];
        sort($tradingDays);

        return hash('sha256', $this->canonicalJson([
            'ruleId' => $rule->id,
            'threshold' => $rule->threshold === null ? null : (string) $rule->threshold,
            'params' => $rule->params,
            'periodStart' => $outcome->periodStart->format('Y-m-d'),
            'periodEnd' => $outcome->periodEnd->format('Y-m-d'),
            'comparisonStart' => $outcome->comparisonStart?->format('Y-m-d'),
            'comparisonEnd' => $outcome->comparisonEnd?->format('Y-m-d'),
            'asOfDate' => $context->asOf?->format('Y-m-d'),
            'lastDueDate' => $context->lastDue->format('Y-m-d'),
            'expectedBranches' => $branches,
            'tradingDays' => $tradingDays,
            'cutoffTime' => $context->calendar['cutoff_time'],
            'cutoffLagDays' => $context->calendar['cutoff_lag_days'],
            // The FULL sorted lists are hashed; the stored evidence may compact them.
            'recordIds' => $recordIds,
            'importIds' => $importIds,
        ]));
    }

    /** Stable key order at every level, no whitespace. */
    public function canonicalJson(mixed $value): string
    {
        return (string) json_encode($this->sortKeys($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->sortKeys($item), $value);
    }
}
