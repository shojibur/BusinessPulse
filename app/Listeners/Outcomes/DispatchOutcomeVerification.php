<?php

namespace App\Listeners\Outcomes;

use App\Events\Intelligence\EvaluationRunCompleted;
use App\Jobs\VerifyOutcomesJob;
use App\Services\Demo\DemoResetFlag;

/** Synchronous listener: one INSERT into the jobs table, nothing else. */
final class DispatchOutcomeVerification
{
    public function handle(EvaluationRunCompleted $event): void
    {
        // A demo reset is deleting this company's data: do nothing (RF-01).
        if (app(DemoResetFlag::class)->isActive()) {
            return;
        }
        VerifyOutcomesJob::dispatch($event->departmentId)->afterCommit();
    }
}
