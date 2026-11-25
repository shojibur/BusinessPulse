<?php

namespace App\Jobs;

use App\Enums\BriefStatus;
use App\Events\Briefing\BriefFailed;
use App\Events\Briefing\BriefGenerated;
use App\Models\Brief;
use App\Services\AI\BriefGenerator;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates one brief on the `ai` queue. The only place provider calls are retried (R6): 3 tries, backoff 10/30/60 s;
 * the client makes one HTTP attempt per call. Unique per company for the wait plus run time, so double clicks and
 * duplicate dispatches cannot create parallel generations. A provider outage ends in a completed fallback brief;
 * `failed` is reserved for non-provider errors.
 */
class GenerateBriefJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    // One chat call (30 s) plus one corrective call fits the budget.
    public int $timeout = 60;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $briefId, public readonly int $companyId)
    {
        $this->onQueue('ai');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'brief:'.$this->companyId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(BriefGenerator $generator): void
    {
        $brief = Brief::query()->find($this->briefId);

        if ($brief === null || in_array($brief->status, [BriefStatus::Completed, BriefStatus::Failed], true)) {
            return; // already finished: a duplicate delivery changes nothing
        }

        $brief->forceFill(['status' => BriefStatus::Generating])->save();

        try {
            $generator->generate($brief, finalAttempt: $this->attempts() >= $this->tries);
        } catch (RecoverableProviderException $e) {
            throw $e; // the queue retries with backoff
        } catch (Throwable $e) {
            $this->markFailed($brief->fresh() ?? $brief, $e);

            return;
        }

        $done = $brief->fresh();
        BriefGenerated::dispatch($done->id, $done->company_id, $done->source?->value ?? 'fallback', $done->items()->count(), $done->requested_by);
    }

    public function failed(Throwable $exception): void
    {
        $brief = Brief::query()->find($this->briefId);

        if ($brief !== null && ! in_array($brief->status, [BriefStatus::Completed, BriefStatus::Failed], true)) {
            $this->markFailed($brief, $exception);
        }
    }

    private function markFailed(Brief $brief, Throwable $e): void
    {
        // Class name only: no messages, bodies or evidence.
        Log::error('brief_generation_failed', ['brief_id' => $brief->id, 'exception' => $e::class]);

        $brief->forceFill(['status' => BriefStatus::Failed, 'error_message' => 'generation_failed'])->save();
        BriefFailed::dispatch($brief->id, $brief->company_id, 'generation_failed', $brief->requested_by);
    }
}
