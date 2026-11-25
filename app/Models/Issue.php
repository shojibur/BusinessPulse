<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ResolutionType;
use App\Models\Concerns\ScopedThroughDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One deduplicated problem of a department. `baseline_evaluation_id` never changes after creation.
 *
 * @property int $id
 * @property int $department_id
 * @property int $business_rule_id
 * @property string $scope_key
 * @property int|null $recurrence_of_issue_id
 * @property string $title
 * @property IssueSeverity $severity
 * @property IssueStatus $status
 * @property Carbon $first_detected_at
 * @property Carbon $last_detected_at
 * @property int $detection_count
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $baseline_evaluation_id
 * @property int $latest_evaluation_id
 * @property Carbon|null $action_started_at
 * @property Carbon|null $monitoring_started_at
 * @property list<array<string, mixed>> $status_history
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property ResolutionType|null $resolution_type
 * @property string|null $resolution_note
 */
class Issue extends Model
{
    use ScopedThroughDepartment;

    public const SCOPE_KEY = 'department';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'action_started_at' => 'datetime',
            'monitoring_started_at' => 'datetime',
            'status_history' => 'array',
            'resolved_at' => 'datetime',
            'resolution_type' => ResolutionType::class,
        ];
    }

    /** @return BelongsTo<BusinessRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(BusinessRule::class, 'business_rule_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<RuleEvaluation, $this> */
    public function baselineEvaluation(): BelongsTo
    {
        return $this->belongsTo(RuleEvaluation::class, 'baseline_evaluation_id');
    }

    /** @return BelongsTo<RuleEvaluation, $this> */
    public function latestEvaluation(): BelongsTo
    {
        return $this->belongsTo(RuleEvaluation::class, 'latest_evaluation_id');
    }

    /** @return BelongsTo<Issue, $this> */
    public function recurrenceOf(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'recurrence_of_issue_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
