<?php

namespace App\Jobs;

use App\Enums\FailureStage;
use App\Enums\MeetingErrorCode;
use App\Enums\MeetingStatus;
use App\Events\Meetings\MeetingProcessingFailed;
use App\Events\Meetings\TranscriptReady;
use App\Models\Meeting;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\AI\TranscriptionService;
use App\Services\Meetings\MeetingRunGuard;
use App\Services\Meetings\MeetingStageException;
use App\Services\Meetings\MeetingStateMachine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transcribes one meeting recording on the `ai` queue. The only retry layer for the provider (R6): 3 tries, backoff
 * 10/30/60 s, one HTTP attempt of 120 s per try inside the 150 s job timeout (worker --timeout 180, retry_after 300).
 * Duplicate dispatches and double clicks are stopped by the unique lock and the status compare-and-set. Authorization,
 * consent and recording state are re-checked here, immediately before any provider call.
 */
class TranscribeMeetingJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $meetingId, public readonly ?int $actorUserId = null)
    {
        $this->onQueue('ai');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "transcribe-{$this->meetingId}";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(MeetingRunGuard $guard, TranscriptionService $service, MeetingStateMachine $states): void
    {
        $meeting = Meeting::query()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        // First attempt: uploaded -> transcribing. A queue retry finds `transcribing` already set.
        $retry = $meeting->status === MeetingStatus::Transcribing && $this->attempts() > 1;

        if (! $retry && ! $states->transition($meeting->id, MeetingStatus::Uploaded, MeetingStatus::Transcribing)) {
            return; // duplicate dispatch or a lost race
        }

        try {
            $meeting = $guard->forTranscription($this->meetingId, $this->actorUserId);

            if ($meeting === null) {
                return;
            }

            $text = $service->transcribe($meeting);
        } catch (RecoverableProviderException $e) {
            if ($this->attempts() < $this->tries) {
                throw $e; // the queue retries with backoff; the status stays `transcribing`
            }

            $this->failStage($states, MeetingErrorCode::fromProvider($e->errorCode()));

            return;
        } catch (MeetingStageException $e) {
            $this->failStage($states, $e->errorCode);

            return;
        }

        $stored = DB::transaction(function () use ($states, $text, $meeting): bool {
            $ok = $states->transition($this->meetingId, MeetingStatus::Transcribing, MeetingStatus::Transcribed, [
                'transcript' => $text, 'transcript_source' => 'audio', 'transcript_edited_at' => null,
            ]);

            if ($ok) {
                TranscriptReady::dispatch($meeting->fresh(), 'audio', null);
            }

            return $ok;
        });

        if ($stored) {
            ExtractMeetingTasksJob::dispatch($this->meetingId, $this->actorUserId);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('transcribe_job_failed', ['meeting_id' => $this->meetingId, 'exception' => $exception::class]);
        $this->failStage(app(MeetingStateMachine::class), MeetingErrorCode::Unexpected);
    }

    private function failStage(MeetingStateMachine $states, MeetingErrorCode $code): void
    {
        $moved = $states->transition(
            $this->meetingId,
            [MeetingStatus::Uploaded, MeetingStatus::Transcribing],
            MeetingStatus::Failed,
            ['failure_stage' => FailureStage::Transcription, 'error_message' => $code->value],
        );

        if ($moved && ($meeting = Meeting::query()->find($this->meetingId)) !== null) {
            MeetingProcessingFailed::dispatch($meeting, FailureStage::Transcription->value, $code->value);
        }
    }
}
