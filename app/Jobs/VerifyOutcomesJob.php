<?php

namespace App\Jobs;

use App\Services\Outcomes\OutcomeVerifier;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies the outcomes of one department after an evaluation run. Database work only (no provider call): 3 tries, backoff
 * 10/30/60 s. Unique until processing starts, so a run that completes while verification is running is not dropped; the
 * issue row locks and the unique outcome key make overlapping or repeated runs safe.
 */
class VerifyOutcomesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $departmentId)
    {
        $this->onQueue('default');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->departmentId;
    }

    public function handle(OutcomeVerifier $verifier): void
    {
        $verifier->verifyDepartment($this->departmentId);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('verify_outcomes_failed', ['department_id' => $this->departmentId, 'exception' => $exception::class]);
    }
}
