<?php

namespace App\Enums;

/** Every action the recorder may write. Core actions are written now; the rest belong to the Polish listeners. */
enum ActivityAction: string
{
    case ImportValidated = 'import.validated';
    case ImportConfirmed = 'import.confirmed';
    case ImportCompleted = 'import.completed';
    case ImportFailed = 'import.failed';
    case ImportCancelled = 'import.cancelled';
    case ImportFileDownloaded = 'import.file_downloaded';
    case RuleEvaluated = 'rule_evaluation.evaluated';
    case BusinessRuleUpdated = 'business_rule.updated';
    case IssueCreated = 'issue.created';
    case IssueRedetected = 'issue.redetected';
    case IssueStatusChanged = 'issue.status_changed';
    case IssueUrgentNotified = 'issue.urgent_notified';
    case BriefRequested = 'brief.requested';
    case BriefGenerated = 'brief.generated';
    case BriefFailed = 'brief.failed';
    case BriefAudioGenerated = 'brief.audio_generated';
    case MeetingCreated = 'meeting.created';
    case MeetingRecordingUploaded = 'meeting.recording_uploaded';
    case MeetingTranscriptReady = 'meeting.transcript_ready';
    case MeetingTranscriptEdited = 'meeting.transcript_edited';
    case MeetingExtractionCompleted = 'meeting.extraction_completed';
    case MeetingProcessingFailed = 'meeting.processing_failed';
    case MeetingRecordingDeleted = 'meeting.recording_deleted';
    case TaskDraftApproved = 'task_draft.approved';
    case TaskDraftRejected = 'task_draft.rejected';
    case TaskAssigned = 'task.assigned';
    case TaskStatusChanged = 'task.status_changed';
    case TaskCompleted = 'task.completed';
    case TaskCommentAdded = 'task.comment_added';
    case OutcomeEvaluated = 'outcome.evaluated';
    case OutcomeNoteSaved = 'outcome.note_saved';
    case FeedbackSubmitted = 'feedback.submitted';
}
