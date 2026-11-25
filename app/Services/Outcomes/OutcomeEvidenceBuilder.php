<?php

namespace App\Services\Outcomes;

use App\Enums\OutcomeMetric;
use App\Enums\OutcomePhase;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;

/** Builds the immutable evidence snapshot (schemaVersion 2) from stored evaluations only; never reads sales_records. */
final class OutcomeEvidenceBuilder
{
    /**
     * @param  array{holds: bool, type: string, limit4: string, threshold: string|null}|null  $condition
     * @return array<string, mixed>
     */
    public function build(BusinessRule $rule, Issue $issue, RuleEvaluation $baseline, RuleEvaluation $followup, OutcomePhase $phase, Assessment $assessment, OutcomeMetric $metric, OutcomeFigures $figures, ?array $condition, bool $resolutionMet, string $timezone): array
    {
        $evidence = [
            'schemaVersion' => 2,
            'ruleKey' => $rule->key->value,
            'outcomeMetric' => $metric->value,
            'phase' => [
                'value' => $phase->value,
                'actionStartedAt' => $issue->action_started_at?->setTimezone($timezone)->toIso8601String(),
                'monitoringStartedAt' => $issue->monitoring_started_at?->setTimezone($timezone)->toIso8601String(),
                'actionDate' => $issue->action_started_at?->setTimezone($timezone)->format('Y-m-d'),
                'monitoringDate' => $issue->monitoring_started_at?->setTimezone($timezone)->format('Y-m-d'),
                'followupPeriodStart' => $followup->period_start->format('Y-m-d'),
                'followupPeriodEnd' => $followup->period_end->format('Y-m-d'),
            ],
            'baseline' => $this->evaluation($baseline, true),
            'followup' => $this->evaluation($followup, false),
            'checks' => $assessment->checks,
            'week' => $assessment->week,
            'band' => OutcomeMetricCalculator::BAND,
            'condition' => $condition === null ? null : [
                'type' => $condition['type'], 'thresholdUsed' => $condition['threshold'], 'limit4' => $condition['limit4'], 'met' => $resolutionMet,
                'valueMet' => $condition['holds'],
            ],
            'values' => $assessment->comparable() ? [
                'baseline' => $figures->baseline, 'followup' => $figures->followup, 'reference' => $figures->reference,
                'absoluteChange' => $figures->absoluteChange, 'relativeChangePct' => $figures->relativeChangePct, 'pointChange' => $figures->pointChange,
            ] : null,
        ];

        if ($metric === OutcomeMetric::WeeklyRevenue) {
            // The rule's week-over-week change is evidence only, never an outcome metric.
            $evidence['weeklyChangeEvidence'] = [
                'baselinePct4' => $baseline->difference_pct === null ? null : (string) $baseline->difference_pct,
                'followupPct4' => $followup->difference_pct === null ? null : (string) $followup->difference_pct,
                'note' => 'rule evidence only, not an outcome metric',
            ];
            $evidence['recoveredSharePct'] = $assessment->comparable() ? $figures->recoveredSharePct : null;
        }

        return $evidence;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluation(RuleEvaluation $e, bool $withReference): array
    {
        $data = [
            'evaluationId' => $e->id,
            'periodStart' => $e->period_start->format('Y-m-d'), 'periodEnd' => $e->period_end->format('Y-m-d'),
            'result' => $e->result->value, 'severity' => $e->severity?->value,
            'currentValue' => $e->current_value === null ? null : (string) $e->current_value,
            'comparisonValue' => $e->comparison_value === null ? null : (string) $e->comparison_value,
            'differencePct4' => $e->difference_pct === null ? null : (string) $e->difference_pct,
            'thresholdUsed' => $e->evidence['thresholdUsed'] ?? null,
            'paramsUsed' => $e->evidence['paramsUsed'] ?? null,
            'expectedBranches' => $e->evidence['coverage']['expectedBranches'] ?? [],
            'contributingImportIds' => $e->evidence['contributingImportIds'] ?? [],
        ];

        if ($withReference && $e->comparison_start !== null) {
            $data['referencePeriod'] = ['start' => $e->comparison_start->format('Y-m-d'), 'end' => $e->comparison_end?->format('Y-m-d')];
        }

        return $data;
    }
}
