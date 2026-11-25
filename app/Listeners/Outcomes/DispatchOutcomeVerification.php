<?php

namespace App\Listeners\Outcomes;

use App\Events\Intelligence\EvaluationRunCompleted;
use App\Jobs\VerifyOutcomesJob;

/** Synchronous listener: one INSERT into the jobs table, nothing else. */
final class DispatchOutcomeVerification
{
    public function handle(EvaluationRunCompleted $event): void
    {
        VerifyOutcomesJob::dispatch($event->departmentId)->afterCommit();
    }
}
