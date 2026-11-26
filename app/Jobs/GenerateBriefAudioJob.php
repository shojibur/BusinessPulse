<?php

namespace App\Jobs;

use App\Enums\AudioStatus;
use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\AI\SpeechService;
use App\Services\Demo\DemoResetFlag;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the audio of one completed brief on the `ai` queue. Same retry layering as GenerateBriefJob; the speech
 * HTTP call is one attempt of 60 s inside a 90 s job. Never fakes success: a failure leaves `audio_status = failed`
 * with a manual retry.
 */
class GenerateBriefAudioJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 90;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $briefId)
    {
        $this->onQueue((string) config('ai.queue', 'default'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'brief-audio:'.$this->briefId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SpeechService $speech): void
    {
        // A demo reset is deleting this company's data: do nothing (RF-01).
        if (app(DemoResetFlag::class)->isActive()) {
            return;
        }
        $brief = Brief::query()->find($this->briefId);

        if ($brief === null || $brief->status !== BriefStatus::Completed || $brief->audio_status === AudioStatus::Ready) {
            return;
        }

        $brief->forceFill(['audio_status' => AudioStatus::Generating])->save();

        try {
            $speech->generate($brief);
        } catch (RecoverableProviderException $e) {
            if ($this->attempts() < $this->tries) {
                throw $e; // the queue retries with backoff
            }

            $this->markFailed($brief, $e->errorCode());
        } catch (ProviderException $e) {
            $this->markFailed($brief, $e->errorCode());
        } catch (Throwable $e) {
            Log::error('brief_audio_failed', ['brief_id' => $brief->id, 'exception' => $e::class]);
            $this->markFailed($brief, 'generation_failed');
        }
    }

    public function failed(Throwable $exception): void
    {
        $brief = Brief::query()->find($this->briefId);

        if ($brief !== null && $brief->audio_status !== AudioStatus::Ready) {
            $this->markFailed($brief, 'generation_failed');
        }
    }

    private function markFailed(Brief $brief, string $code): void
    {
        $brief->forceFill(['audio_status' => AudioStatus::Failed])->save();
        Log::warning('brief_audio_failed', ['brief_id' => $brief->id, 'code' => $code]);
    }
}
