<?php

namespace App\Models;

use App\Enums\InsufficientReason;
use App\Enums\IssueSeverity;
use App\Enums\RuleResult;
use App\Models\Concerns\ScopedThroughDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One immutable evaluation of a rule with its full evidence snapshot. Rows are never updated after insert.
 *
 * @property int $id
 * @property int $business_rule_id
 * @property int $department_id
 * @property int|null $import_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $comparison_start
 * @property Carbon|null $comparison_end
 * @property string $metric_name
 * @property string|null $current_value
 * @property string|null $comparison_value
 * @property string|null $difference_value
 * @property string|null $difference_pct
 * @property RuleResult $result
 * @property IssueSeverity|null $severity
 * @property InsufficientReason|null $insufficient_reason
 * @property Carbon|null $as_of_date
 * @property Carbon $last_due_date
 * @property array<string, mixed> $evidence
 * @property Carbon $evaluated_at
 * @property string $run_key
 */
class RuleEvaluation extends Model
{
    use ScopedThroughDepartment;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'comparison_start' => 'date:Y-m-d',
            'comparison_end' => 'date:Y-m-d',
            'current_value' => 'decimal:2',
            'comparison_value' => 'decimal:2',
            'difference_value' => 'decimal:2',
            'difference_pct' => 'decimal:4',
            'result' => RuleResult::class,
            'severity' => IssueSeverity::class,
            'insufficient_reason' => InsufficientReason::class,
            'as_of_date' => 'date:Y-m-d',
            'last_due_date' => 'date:Y-m-d',
            'evidence' => 'array',
            'evaluated_at' => 'datetime',
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
}
