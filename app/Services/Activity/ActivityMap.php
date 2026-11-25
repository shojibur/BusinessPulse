<?php

namespace App\Services\Activity;

use App\Enums\ActivityAction;
use App\Enums\ActivitySubjectType;
use App\Events\Briefing\BriefFailed;
use App\Events\Briefing\BriefGenerated;
use App\Events\Imports\ImportCompleted;
use App\Events\Imports\ImportFailed;
use App\Events\Intelligence\IssueCreated;
use App\Events\Intelligence\IssueStatusChanged;
use App\Events\Intelligence\UrgentIssueNotified;
use App\Events\Meetings\ExtractionCompleted;
use App\Events\Meetings\MeetingProcessingFailed;
use App\Events\Meetings\TranscriptReady;
use App\Events\Outcomes\OutcomeEvaluated;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskDraftApproved;
use App\Events\Tasks\TaskStatusChanged;

/**
 * The declarative event to activity mapping of the CORE listeners (design "Event to activity mapping"). Each entry names the
 * action, the subject and how to read ids and properties from the event. Polish events are not mapped yet.
 */
final class ActivityMap
{
    /**
     * @return array<class-string, array{action: ActivityAction, subject: ActivitySubjectType, subjectId: callable, issueIds?: callable, properties?: callable}>
     */
    public static function core(): array
    {
        $date = fn ($d) => $d?->format('Y-m-d');

        return [
            ImportCompleted::class => ['action' => ActivityAction::ImportCompleted, 'subject' => ActivitySubjectType::Import, 'subjectId' => fn ($e) => $e->importId,
                'properties' => fn ($e) => ['status' => $e->status->value, 'insertedRows' => $e->insertedRows, 'correctedRows' => $e->correctedRows, 'unchangedRows' => $e->unchangedRows,
                    'invalidRows' => $e->invalidRows, 'minDate' => $date($e->minDate), 'maxDate' => $date($e->maxDate)]],
            ImportFailed::class => ['action' => ActivityAction::ImportFailed, 'subject' => ActivitySubjectType::Import, 'subjectId' => fn ($e) => $e->importId],
            IssueCreated::class => ['action' => ActivityAction::IssueCreated, 'subject' => ActivitySubjectType::Issue, 'subjectId' => fn ($e) => $e->issueId, 'issueIds' => fn ($e) => [$e->issueId],
                'properties' => fn ($e) => ['severity' => $e->severity, 'recurrenceOfIssueId' => $e->recurrenceOfIssueId]],
            IssueStatusChanged::class => ['action' => ActivityAction::IssueStatusChanged, 'subject' => ActivitySubjectType::Issue, 'subjectId' => fn ($e) => $e->issueId, 'issueIds' => fn ($e) => [$e->issueId],
                'properties' => fn ($e) => ['from' => $e->from, 'to' => $e->to, 'hasReason' => $e->reason !== null]],
            UrgentIssueNotified::class => ['action' => ActivityAction::IssueUrgentNotified, 'subject' => ActivitySubjectType::Issue, 'subjectId' => fn ($e) => $e->issueId, 'issueIds' => fn ($e) => [$e->issueId],
                'properties' => fn ($e) => ['severity' => $e->severity, 'recipientCount' => count($e->recipientUserIds)]],
            BriefGenerated::class => ['action' => ActivityAction::BriefGenerated, 'subject' => ActivitySubjectType::Brief, 'subjectId' => fn ($e) => $e->briefId,
                'properties' => fn ($e) => ['source' => $e->source, 'itemCount' => $e->itemCount]],
            BriefFailed::class => ['action' => ActivityAction::BriefFailed, 'subject' => ActivitySubjectType::Brief, 'subjectId' => fn ($e) => $e->briefId, 'properties' => fn ($e) => ['errorCode' => $e->reason]],
            TranscriptReady::class => ['action' => ActivityAction::MeetingTranscriptReady, 'subject' => ActivitySubjectType::Meeting, 'subjectId' => fn ($e) => $e->meetingId, 'issueIds' => fn ($e) => $e->issueIds,
                'properties' => fn ($e) => ['transcriptSource' => $e->transcriptSource]],
            ExtractionCompleted::class => ['action' => ActivityAction::MeetingExtractionCompleted, 'subject' => ActivitySubjectType::Meeting, 'subjectId' => fn ($e) => $e->meetingId, 'issueIds' => fn ($e) => $e->issueIds,
                'properties' => fn ($e) => ['extractionRun' => $e->extractionRun, 'decisionCount' => $e->decisionCount, 'taskDraftCount' => $e->taskDraftCount]],
            MeetingProcessingFailed::class => ['action' => ActivityAction::MeetingProcessingFailed, 'subject' => ActivitySubjectType::Meeting, 'subjectId' => fn ($e) => $e->meetingId, 'issueIds' => fn ($e) => $e->issueIds,
                'properties' => fn ($e) => ['stage' => $e->stage, 'errorCode' => $e->errorCode]],
            TaskDraftApproved::class => ['action' => ActivityAction::TaskDraftApproved, 'subject' => ActivitySubjectType::Task, 'subjectId' => fn ($e) => $e->taskId, 'issueIds' => fn ($e) => $e->issueId === null ? [] : [$e->issueId],
                'properties' => fn ($e) => ['taskDraftId' => $e->taskDraftId, 'assigneeId' => $e->assigneeId]],
            TaskAssigned::class => ['action' => ActivityAction::TaskAssigned, 'subject' => ActivitySubjectType::Task, 'subjectId' => fn ($e) => $e->taskId, 'issueIds' => fn ($e) => $e->issueId === null ? [] : [$e->issueId],
                'properties' => fn ($e) => ['assigneeId' => $e->assigneeId, 'previousAssigneeId' => $e->previousAssigneeId]],
            TaskStatusChanged::class => ['action' => ActivityAction::TaskStatusChanged, 'subject' => ActivitySubjectType::Task, 'subjectId' => fn ($e) => $e->taskId, 'issueIds' => fn ($e) => $e->issueId === null ? [] : [$e->issueId],
                'properties' => fn ($e) => ['from' => $e->from, 'to' => $e->to]],
            TaskCompleted::class => ['action' => ActivityAction::TaskCompleted, 'subject' => ActivitySubjectType::Task, 'subjectId' => fn ($e) => $e->taskId, 'issueIds' => fn ($e) => $e->issueId === null ? [] : [$e->issueId],
                'properties' => fn ($e) => ['assigneeId' => $e->assigneeId]],
            OutcomeEvaluated::class => ['action' => ActivityAction::OutcomeEvaluated, 'subject' => ActivitySubjectType::Outcome, 'subjectId' => fn ($e) => $e->outcomeId, 'issueIds' => fn ($e) => [$e->issueId],
                'properties' => fn ($e) => ['result' => $e->result, 'phase' => $e->phase, 'comparable' => $e->comparable, 'resolutionConditionMet' => $e->resolutionConditionMet, 'lifecycleAction' => $e->lifecycleAction]],
        ];
    }
}
