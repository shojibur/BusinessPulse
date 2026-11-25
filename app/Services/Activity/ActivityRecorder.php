<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;

/** The only writer of activity_logs. Filters properties through the allow-list; never logs them. */
class ActivityRecorder
{
    public function __construct(private readonly PropertyAllowList $allowList) {}

    public function record(ActivityEntry $entry): void
    {
        ActivityLog::query()->create([
            'company_id' => $entry->companyId,
            'department_id' => $entry->departmentId,
            'user_id' => $entry->userId,
            'action' => $entry->action->value,
            'subject_type' => $entry->subjectType->value,
            'subject_id' => $entry->subjectId,
            'issue_id' => $entry->issueId,
            'properties' => (object) $this->allowList->filter($entry->action, $entry->properties),
            'created_at' => $entry->createdAt,
        ]);
    }
}
