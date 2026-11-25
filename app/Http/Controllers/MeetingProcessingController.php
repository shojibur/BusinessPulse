<?php

namespace App\Http\Controllers;

use App\Enums\FailureStage;
use App\Enums\MeetingStatus;
use App\Http\Requests\ReExtractMeetingRequest;
use App\Http\Requests\RetryMeetingRequest;
use App\Jobs\ExtractMeetingTasksJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Services\Meetings\MeetingStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class MeetingProcessingController extends Controller
{
    /** Retry the stage that failed. Compare-and-set: only one of two simultaneous clicks wins. */
    public function retry(RetryMeetingRequest $request, Meeting $meeting, MeetingStateMachine $states): RedirectResponse
    {
        $stage = $meeting->status === MeetingStatus::Failed ? $meeting->failure_stage : null;

        $moved = match ($stage) {
            // Only when the recording still exists and consent is recorded; the job re-checks both when it runs.
            FailureStage::Transcription => $meeting->recording_path !== null && $meeting->recording_deleted_at === null && $meeting->recording_consent_at !== null
                && $states->transition($meeting->id, MeetingStatus::Failed, MeetingStatus::Uploaded, [], FailureStage::Transcription),
            FailureStage::Extraction => $states->transition($meeting->id, MeetingStatus::Failed, MeetingStatus::Extracting, [], FailureStage::Extraction),
            default => false,
        };

        if (! $moved) {
            throw ValidationException::withMessages(['retry' => 'This meeting cannot be retried right now.']);
        }

        $stage === FailureStage::Transcription
            ? TranscribeMeetingJob::dispatch($meeting->id, $request->user()->id)
            : ExtractMeetingTasksJob::dispatch($meeting->id, $request->user()->id);

        return to_route('meetings.show', $meeting);
    }

    /**
     * Re-extract from the CURRENT transcript. Pending drafts of the earlier run become `superseded` (never deleted);
     * approved and rejected drafts and the tasks created from them are untouched. S07 needs this so a changed
     * transcript can never leave stale drafts approvable.
     */
    public function reExtract(ReExtractMeetingRequest $request, Meeting $meeting, MeetingStateMachine $states): RedirectResponse
    {
        if (! $states->transition($meeting->id, MeetingStatus::ReadyForReview, MeetingStatus::Extracting)) {
            throw ValidationException::withMessages(['retry' => 'This meeting cannot be re-extracted right now.']);
        }

        ExtractMeetingTasksJob::dispatch($meeting->id, $request->user()->id);

        return to_route('meetings.show', $meeting);
    }
}
