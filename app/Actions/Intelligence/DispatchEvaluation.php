<?php

namespace App\Actions\Intelligence;

use App\Jobs\EvaluateRulesJob;

/**
 * The single place that queues an evaluation: used by the import listener and by Refresh intelligence.
 */
class DispatchEvaluation
{
    public function handle(int $departmentId, ?int $importId = null, ?int $actorUserId = null): void
    {
        EvaluateRulesJob::dispatch($departmentId, $importId, $actorUserId);
    }
}
