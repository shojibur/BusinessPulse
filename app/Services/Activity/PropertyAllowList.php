<?php

namespace App\Services\Activity;

use App\Enums\ActivityAction;

/**
 * Per-action allow-list of property keys. Only scalar values survive (int, bool, null, or a string of at most 100
 * characters): no arrays, no long text, so a transcript, a note, a CSV row, a key or an email cannot be stored by accident.
 * A dropped property is silent and never prevents the row.
 */
final class PropertyAllowList
{
    private const KEYS = [
        'import.completed' => ['status', 'insertedRows', 'correctedRows', 'unchangedRows', 'invalidRows', 'minDate', 'maxDate'],
        'rule_evaluation.evaluated' => ['ruleKey', 'result', 'severity', 'importId'],
        'issue.created' => ['severity', 'recurrenceOfIssueId'],
        'issue.redetected' => ['detectionCount', 'previousSeverity', 'newSeverity'],
        'issue.status_changed' => ['from', 'to', 'hasReason'],
        'issue.urgent_notified' => ['severity', 'recipientCount'],
        'brief.generated' => ['source', 'itemCount'],
        'brief.failed' => ['errorCode'],
        'meeting.recording_uploaded' => ['sizeBytes', 'mime'],
        'meeting.transcript_ready' => ['transcriptSource'],
        'meeting.extraction_completed' => ['extractionRun', 'decisionCount', 'taskDraftCount'],
        'meeting.processing_failed' => ['stage', 'errorCode'],
        'meeting.recording_deleted' => ['reason'],
        'task_draft.approved' => ['taskDraftId', 'assigneeId'],
        'task_draft.rejected' => ['hasReason'],
        'task.assigned' => ['assigneeId', 'previousAssigneeId'],
        'task.status_changed' => ['from', 'to'],
        'task.completed' => ['assigneeId'],
        'task.comment_added' => ['commentId'],
        'outcome.evaluated' => ['result', 'phase', 'comparable', 'resolutionConditionMet', 'lifecycleAction'],
        'feedback.submitted' => ['targetType', 'useful', 'hasNote', 'isUpdate'],
    ];

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, int|bool|string|null>
     */
    public function filter(ActivityAction $action, array $raw): array
    {
        $allowed = self::KEYS[$action->value] ?? [];
        $clean = [];

        foreach ($raw as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            if (is_int($value) || is_bool($value) || $value === null || (is_string($value) && mb_strlen($value) <= 100)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
