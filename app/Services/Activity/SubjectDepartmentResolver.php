<?php

namespace App\Services\Activity;

use App\Enums\ActivitySubjectType;
use Illuminate\Support\Facades\DB;

/** The department of a subject when the event does not carry one. Null is allowed. */
final class SubjectDepartmentResolver
{
    public function resolve(ActivitySubjectType $type, int $id): ?int
    {
        $value = match ($type) {
            ActivitySubjectType::Import => DB::table('imports')->where('id', $id)->value('department_id'),
            ActivitySubjectType::RuleEvaluation => DB::table('rule_evaluations')->where('id', $id)->value('department_id'),
            ActivitySubjectType::BusinessRule => DB::table('business_rules')->where('id', $id)->value('department_id'),
            ActivitySubjectType::Issue => DB::table('issues')->where('id', $id)->value('department_id'),
            ActivitySubjectType::Brief => DB::table('briefs')->where('id', $id)->value('department_id'),
            ActivitySubjectType::Meeting => DB::table('meetings')->where('id', $id)->value('department_id'),
            ActivitySubjectType::Task => DB::table('tasks')->where('id', $id)->value('department_id'),
            ActivitySubjectType::TaskDraft => DB::table('task_drafts')->join('meetings', 'meetings.id', '=', 'task_drafts.meeting_id')->where('task_drafts.id', $id)->value('meetings.department_id'),
            ActivitySubjectType::Outcome => DB::table('outcomes')->join('issues', 'issues.id', '=', 'outcomes.issue_id')->where('outcomes.id', $id)->value('issues.department_id'),
        };

        return $value === null ? null : (int) $value;
    }
}
