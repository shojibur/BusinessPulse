<?php

namespace App\Services\Meetings;

use App\Enums\MeetingStatus;
use App\Enums\TaskDraftStatus;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the explicit Inertia props of the meeting pages. The recording path, raw error text and provider data never
 * appear in them; only authorized route URLs do.
 */
final class MeetingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function detail(Meeting $meeting, User $user): array
    {
        $meeting->loadMissing(['department.company', 'creator:id,name', 'consentGivenBy:id,name', 'issues:id,title']);
        $timezone = $meeting->department->company->timezone;
        $local = fn ($time) => $time?->setTimezone($timezone)->toIso8601String();
        $present = $meeting->recording_path !== null && $meeting->recording_deleted_at === null;

        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'superseded' => 0];
        foreach ($meeting->taskDrafts()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
            $counts[$status] = (int) $total;
        }

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'meetingDate' => $meeting->meeting_date->format('Y-m-d'),
            'department' => ['id' => $meeting->department->id, 'name' => $meeting->department->name],
            'createdBy' => $meeting->creator?->name,
            'status' => $meeting->status->value,
            'statusLabel' => $meeting->status->label(),
            'failureStage' => $meeting->failure_stage?->value,
            'errorCode' => $meeting->error_message,
            'errorMessage' => MeetingErrorPresenter::message($meeting->error_message),
            'hasTranscript' => $meeting->transcript !== null,
            'transcriptSource' => $meeting->transcript_source?->value,
            'transcriptEditedAt' => $local($meeting->transcript_edited_at),
            'transcriptChangedSinceExtraction' => $meeting->transcript_edited_at !== null
                && ($meeting->extracted_at === null || $meeting->transcript_edited_at->gt($meeting->extracted_at)),
            'extractionRun' => (int) $meeting->taskDrafts()->max('extraction_run'),
            'discardedCount' => $meeting->extraction_discarded_count,
            'discardedNotice' => $meeting->extraction_discarded_count > 0
                ? $meeting->extraction_discarded_count.' item'.($meeting->extraction_discarded_count === 1 ? ' was' : 's were').' removed because their quoted text was not found in the transcript or they duplicate an approved draft.'
                : null,
            'recording' => [
                'present' => $present,
                'originalName' => $meeting->recording_original_name,
                'mime' => $meeting->recording_mime,
                'sizeBytes' => $meeting->recording_size,
                'uploadedAt' => $local($meeting->recording_uploaded_at),
                'consentAt' => $local($meeting->recording_consent_at),
                'consentBy' => $meeting->consentGivenBy?->name,
                'deletedAt' => $local($meeting->recording_deleted_at),
                'streamUrl' => $present ? route('meetings.recording.stream', $meeting) : null,
                'downloadUrl' => $present ? route('meetings.recording.download', $meeting) : null,
            ],
            'linkedIssues' => $meeting->issues->map(fn ($issue) => ['id' => $issue->id, 'title' => $issue->title, 'url' => route('issues.show', $issue)])->all(),
            'decisions' => $meeting->decisions ?? [],
            'draftCounts' => $counts,
            'retentionDays' => $meeting->department->company->recording_retention_days,
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $meeting),
                'uploadRecording' => Gate::forUser($user)->allows('uploadRecording', $meeting)
                    && ($meeting->status === MeetingStatus::Draft || ($meeting->status === MeetingStatus::Failed && $meeting->failure_stage?->value === 'transcription'))
                    && ! $present && $meeting->transcript === null,
                'enterTranscript' => Gate::forUser($user)->allows('enterTranscript', $meeting)
                    && ($meeting->status === MeetingStatus::Draft || ($meeting->status === MeetingStatus::Failed && $meeting->failure_stage?->value === 'transcription')),
                'editTranscript' => Gate::forUser($user)->allows('editTranscript', $meeting)
                    && $meeting->transcript !== null && in_array($meeting->status, [MeetingStatus::Transcribed, MeetingStatus::ReadyForReview, MeetingStatus::Failed], true),
                'retry' => Gate::forUser($user)->allows('retry', $meeting) && $meeting->status === MeetingStatus::Failed
                    && ($meeting->failure_stage?->value !== 'transcription' || ($present && $meeting->recording_consent_at !== null)),
                'reExtract' => Gate::forUser($user)->allows('reExtract', $meeting) && $meeting->status === MeetingStatus::ReadyForReview,
                'deleteRecording' => Gate::forUser($user)->allows('deleteRecording', $meeting) && $present && $meeting->status !== MeetingStatus::Transcribing,
            ],
            'polling' => $meeting->status->polling(),
        ];
    }

    /**
     * Read-only draft rows for the Task drafts tab (S07 extends the tab itself).
     *
     * @return list<array<string, mixed>>
     */
    public function drafts(Meeting $meeting): array
    {
        return $meeting->taskDrafts()->with('assignee:id,name')
            ->whereIn('status', [TaskDraftStatus::Pending->value, TaskDraftStatus::Approved->value, TaskDraftStatus::Rejected->value])
            ->orderByDesc('extraction_run')->orderBy('id')->get()
            ->map(fn (TaskDraft $draft) => [
                'id' => $draft->id,
                'title' => $draft->suggested_title,
                'description' => $draft->suggested_description,
                'excerpt' => $draft->transcript_excerpt,
                'assignee' => [
                    'resolution' => $draft->assignee_resolution->value,
                    'name' => $draft->assignee?->name,
                    'suggestedName' => $draft->suggested_assignee_name,
                ],
                'deadline' => [
                    'resolution' => $draft->deadline_resolution->value,
                    'date' => $draft->due_date?->format('Y-m-d'),
                    'suggestedText' => $draft->suggested_due_date_text,
                ],
                'status' => $draft->status->value,
            ])->all();
    }

    public function recordingExists(Meeting $meeting): bool
    {
        return $meeting->recording_path !== null && Storage::disk('local')->exists($meeting->recording_path);
    }
}
