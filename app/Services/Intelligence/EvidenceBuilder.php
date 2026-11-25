<?php

namespace App\Services\Intelligence;

use App\Models\BusinessRule;

/**
 * The JSONB snapshot stored with every evaluation. Money is a decimal string, percentages are 4-decimal strings.
 * Once stored it is never changed, so later corrections to sales records cannot alter past evidence.
 */
final class EvidenceBuilder
{
    /** Above this many ids the snapshot stores compact [min, max] ranges (the run key still hashes every id). */
    public const COMPACT_ABOVE = 1000;

    /**
     * @return array<string, mixed>
     */
    public function build(BusinessRule $rule, EvaluationContext $context, EvaluationOutcome $outcome): array
    {
        return [
            'asOfDate' => $context->asOf?->format('Y-m-d'),
            'lastDueDate' => $context->lastDue->format('Y-m-d'),
            'thresholdUsed' => $rule->threshold === null ? null : (string) $rule->threshold,
            'paramsUsed' => $rule->params,
            'calendar' => [
                'tradingDays' => $context->calendar['trading_days'],
                'cutoffTime' => $context->calendar['cutoff_time'],
                'cutoffLagDays' => $context->calendar['cutoff_lag_days'],
                'timezone' => $context->calendar['timezone'],
            ],
            'periods' => $outcome->periods,
            'coverage' => $outcome->coverage,
            'contributingImportIds' => $outcome->importIds,
            'contributingRecordIds' => $this->compact($outcome->recordIds),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>|list<array{0: int, 1: int}>
     */
    public function compact(array $ids): array
    {
        sort($ids);

        if (count($ids) <= self::COMPACT_ABOVE) {
            return $ids;
        }

        $ranges = [];
        foreach ($ids as $id) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last][1] + 1 === $id) {
                $ranges[$last][1] = $id;
            } else {
                $ranges[] = [$id, $id];
            }
        }

        return $ranges;
    }
}
