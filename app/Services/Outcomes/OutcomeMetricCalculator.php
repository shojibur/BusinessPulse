<?php

namespace App\Services\Outcomes;

use App\Enums\OutcomeMetric;
use App\Enums\OutcomeResult;
use App\Models\RuleEvaluation;
use App\Services\Intelligence\Indeterminate;
use App\Services\Intelligence\MetricCalculator;

/**
 * Deterministic outcome figures from two stored evaluations (bcmath, no floats). The outcome of the decline rule is the
 * revenue LEVEL in MYR compared with the immutable baseline week and the pre-decline reference week. The week-over-week
 * change of the rule is never a value or the classified change. Percentage metrics use percentage-point changes computed
 * from the 2-decimal display values (so 96.15 - 79.49 = +16.66).
 */
final class OutcomeMetricCalculator
{
    public const BAND = '2.00';

    public function __construct(private readonly MetricCalculator $metrics) {}

    public function compute(OutcomeMetric $metric, RuleEvaluation $baseline, RuleEvaluation $followup): OutcomeFigures
    {
        if ($metric === OutcomeMetric::WeeklyRevenue) {
            $base = (string) $baseline->current_value;
            $follow = (string) $followup->current_value;
            $reference = (string) $baseline->comparison_value;
            $relative = $this->metrics->weeklyChangePct($follow, $base); // baseline is non-zero (checked by the assessor)
            $relative2 = $relative instanceof Indeterminate ? '0.00' : $this->metrics->display2($relative);
            $share = $this->metrics->pct($follow, $reference);

            return new OutcomeFigures(
                $base, $follow, $reference, bcsub($follow, $base, 2), $relative2, null,
                $this->classify($relative2),
                $share instanceof Indeterminate ? null : $this->metrics->display2($share),
            );
        }

        $base = $this->metrics->display2((string) $baseline->difference_pct);
        $follow = $this->metrics->display2((string) $followup->difference_pct);
        $points = bcsub($follow, $base, 2);

        return new OutcomeFigures($base, $follow, null, null, null, $points, $this->classify($points));
    }

    /** Inclusive +/-2.00 bands; negative zero is normalised. */
    public function classify(string $change2): OutcomeResult
    {
        $change2 = bccomp($change2, '0', 2) === 0 ? '0.00' : $change2;

        return match (true) {
            bccomp($change2, self::BAND, 2) >= 0 => OutcomeResult::Improved,
            bccomp($change2, '-'.self::BAND, 2) <= 0 => OutcomeResult::Worsened,
            default => OutcomeResult::Unchanged,
        };
    }

    /**
     * Whether the level condition for resolution holds, using the threshold recorded in the BASELINE evaluation (so a
     * later threshold change cannot move the goalposts). Independent of the phase.
     *
     * @return array{holds: bool, type: string, limit4: string, threshold: string|null}
     */
    public function condition(OutcomeMetric $metric, RuleEvaluation $baseline, RuleEvaluation $followup, OutcomeFigures $figures): array
    {
        $threshold = $baseline->evidence['thresholdUsed'] ?? null;
        $t = $threshold === null ? '0' : (string) $threshold;

        return match ($metric) {
            OutcomeMetric::WeeklyRevenue => (function () use ($figures, $t, $threshold): array {
                // reference x (1 - T/100), at 4 decimals
                $limit = bcdiv(bcmul((string) $figures->reference, bcsub('100', $t, 2), 6), '100', 4);

                return ['holds' => bccomp((string) $figures->followup, $limit, 4) >= 0, 'type' => 'minimum_revenue', 'limit4' => $limit, 'threshold' => $threshold];
            })(),
            OutcomeMetric::TargetAchievement => ['holds' => bccomp((string) $followup->difference_pct, $t, 4) >= 0, 'type' => 'minimum_percentage', 'limit4' => bcadd($t, '0', 4), 'threshold' => $threshold],
            OutcomeMetric::Coverage => ['holds' => bccomp((string) $followup->difference_pct, '100', 4) === 0, 'type' => 'full_coverage', 'limit4' => '100.0000', 'threshold' => $threshold],
        };
    }
}
