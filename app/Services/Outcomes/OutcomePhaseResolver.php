<?php

namespace App\Services\Outcomes;

use App\Enums\OutcomePhase;
use Carbon\CarbonImmutable;

/**
 * Pure: classifies a follow-up reporting period against the action and monitoring dates of the issue (R3). Dates are in the
 * company timezone; the current time is never used.
 */
final class OutcomePhaseResolver
{
    public function resolve(?CarbonImmutable $actionStartedAt, ?CarbonImmutable $monitoringStartedAt, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, string $timezone): OutcomePhase
    {
        $action = $actionStartedAt?->setTimezone($timezone)->format('Y-m-d');
        $monitoring = $monitoringStartedAt?->setTimezone($timezone)->format('Y-m-d');

        if ($action === null || $periodEnd->format('Y-m-d') < $action) {
            return OutcomePhase::PreAction;
        }

        // The period must start STRICTLY after the day monitoring began; a period overlapping it is still during action.
        if ($monitoring !== null && $periodStart->format('Y-m-d') > $monitoring) {
            return OutcomePhase::PostAction;
        }

        return OutcomePhase::DuringAction;
    }
}
