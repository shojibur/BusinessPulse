<?php

namespace App\Jobs;

use App\Enums\FailureStage;
use App\Enums\MeetingErrorCode;
use App\Enums\MeetingStatus;
use App\Enums\TaskDraftStatus;
use App\Events\Meetings\ExtractionCompleted;
use App\Events\Meetings\MeetingProcessingFailed;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\AI\MeetingTaskExtractor;
use App\Services\Demo\DemoResetFlag;
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
 * Extracts decisions and task DRAFTS from the current transcript on the `ai` queue. It creates drafts only: never a
 * task, an assignment, an approval, an issue status change or a notification. Retry layering as TranscribeMeetingJob:
 * 3 tries, backoff 10/30/60 s, HTTP 60 s per request with a corrective request inside an 80 s budget, job timeout 90 s
 * (at most 2 provider calls per try, 6 per user action). A result is stored only if the transcript is unchanged since the
 * run started, so a stale extraction never replaces results of a newer transcript.
 */
class ExtractMeetingTasksJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $meetingId, public readonly ?int $actorUserId = null)
    {
        $this->onQueue('ai');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "extract-{$this->meetingId}";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(MeetingRunGuard $guard, MeetingTaskExtractor $extractor, MeetingStateMachine $states): void
    {
        // A demo reset is deleting this company's data: do nothing (RF-01).
        if (app(DemoResetFlag::class)->isActive()) {
            return;
        }
        $meeting = Meeting::query()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        // Inputs: `transcribed` (automatic first run) or `extracting` (set by Retry or Re-extract, or an earlier attempt).
        if ($meeting->status === MeetingStatus::Transcribed) {
            if (! $states->transition($meeting->id, MeetingStatus::Transcribed, MeetingStatus::Extracting)) {
                return;
            }
        } elseif ($meeting->status !== MeetingStatus::Extracting) {
            return; // duplicate dispatch
        }

        try {
            $meeting = $guard->forExtraction($this->meetingId, $this->actorUserId);

            if ($meeting === null) {
                return;
            }

            $revision = $this->revision($meeting);
            $staff = $guard->allowedStaff($meeting);
            $approved = TaskDraft::query()->where('meeting_id', $meeting->id)->where('status', TaskDraftStatus::Approved->value)->get();
            $result = $extractor->extract($meeting, $staff, $approved);
        } catch (RecoverableProviderException $e) {
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            $this->failStage($states, MeetingErrorCode::fromProvider($e->errorCode()));

            return;
        } catch (MeetingStageException $e) {
            $this->failStage($states, $e->errorCode);

            return;
        }

        $changed = false;

        DB::transaction(function () use ($meeting, $result, $states, $revision, &$changed): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== MeetingStatus::Extracting) {
                return;
            }

            if ($this->revision($locked) !== $revision) {
                $changed = true; // the transcript moved on: this result is stale and is not stored

                return;
            }

            $run = (int) TaskDraft::query()->where('meeting_id', $meeting->id)->max('extraction_run') + 1;

            TaskDraft::query()->where('meeting_id', $meeting->id)->where('status', TaskDraftStatus::Pending->value)
                ->update(['status' => TaskDraftStatus::Superseded->value]);

            foreach ($result->drafts as $draft) {
                TaskDraft::query()->create([
                    'meeting_id' => $meeting->id,
                    'issue_id' => null,
                    'suggested_title' => $draft->title,
                    'suggested_description' => $draft->description,
                    'suggested_assignee_name' => $draft->rawAssigneeName,
                    'assignee_id' => $draft->assignee->userId,
                    'assignee_resolution' => $draft->assignee->resolution->value,
                    'suggested_due_date_text' => $draft->rawDeadlinePhrase,
                    'suggested_due_date' => $draft->deadline->date?->format('Y-m-d'),
                    'due_date' => $draft->deadline->date?->format('Y-m-d'),
                    'deadline_resolution' => $draft->deadline->resolution->value,
                    'transcript_excerpt' => $draft->excerpt,
                    'transcript_revision' => $revision,
                    'status' => TaskDraftStatus::Pending->value,
                    'extraction_run' => $run,
                ]);
            }

            $states->transition($meeting->id, MeetingStatus::Extracting, MeetingStatus::ReadyForReview, [
                'decisions' => $result->decisions,
                'extraction_discarded_count' => $result->discardedCount,
                'extracted_at' => now(),
            ]);

            ExtractionCompleted::dispatch($locked->fresh(), $run, count($result->decisions), count($result->drafts));
        });

        if ($changed) {
            $this->failStage($states, MeetingErrorCode::TranscriptChanged);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('extract_job_failed', ['meeting_id' => $this->meetingId, 'exception' => $exception::class]);
        $this->failStage(app(MeetingStateMachine::class), MeetingErrorCode::Unexpected);
    }

    /** Identifies the transcript revision a run started from. */
    private function revision(Meeting $meeting): string
    {
        return sha1((string) $meeting->transcript);
    }

    private function failStage(MeetingStateMachine $states, MeetingErrorCode $code): void
    {
        $moved = $states->transition(
            $this->meetingId,
            [MeetingStatus::Transcribed, MeetingStatus::Extracting],
            MeetingStatus::Failed,
            ['failure_stage' => FailureStage::Extraction, 'error_message' => $code->value],
        );

        if ($moved && ($meeting = Meeting::query()->find($this->meetingId)) !== null) {
            MeetingProcessingFailed::dispatch($meeting, FailureStage::Extraction->value, $code->value);
        }
    }
}
