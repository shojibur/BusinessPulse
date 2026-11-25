<?php

namespace App\Services\Outcomes;

use App\Models\Outcome;

/** Builds display data of an outcome from the stored row and its evidence only (never from sales data). */
final class OutcomePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Outcome $o, string $timezone): array
    {
        $money = $o->outcome_metric->isMoney();
        $at = $o->evaluated_at->setTimezone($timezone);

        return [
            'id' => $o->id,
            'result' => $o->result->value,
            'resultLabel' => OutcomeWording::result($o->result),
            'phase' => $o->phase->value,
            'phaseLabel' => OutcomeWording::phase($o->phase),
            'usedForVerification' => $o->phase->usedForVerification(),
            'outcomeMetricLabel' => OutcomeWording::metric($o->outcome_metric),
            'baselineLabel' => $money ? OutcomeWording::money($o->baseline_value) : ($o->baseline_value === null ? null : $o->baseline_value.'%'),
            'followupLabel' => $money ? OutcomeWording::money($o->followup_value) : ($o->followup_value === null ? null : $o->followup_value.'%'),
            'changeLabels' => $o->result->value === 'not_evaluable' ? [] : ($money
                ? [OutcomeWording::signedMoney($o->absolute_change), OutcomeWording::signedPercent($o->relative_change_pct)]
                : [OutcomeWording::signedPoints($o->point_change)]),
            'evaluatedAt' => $at->toIso8601String(),
            'evaluatedAtLabel' => $at->format('j M Y, H:i'),
            'resolutionConditionMet' => $o->resolution_condition_met,
            'notEvaluableText' => OutcomeWording::reason($o->not_evaluable_reason),
            'manualNote' => $o->manual_note,
            'url' => route('outcomes.show', $o->id),
        ];
    }
}
