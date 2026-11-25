<?php

namespace App\Models;

use App\Enums\NotEvaluableReason;
use App\Enums\OutcomeMetric;
use App\Enums\OutcomePhase;
use App\Enums\OutcomeResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An observed change after actions, for one issue and one follow-up evaluation. Insert-only: the baseline, the values and
 * the evidence snapshot never change, so later corrected imports cannot rewrite what was observed. Only the manual note
 * columns may be updated. All value columns are decimal strings (never floats).
 *
 * @property int $id
 * @property int $issue_id
 * @property int $baseline_evaluation_id
 * @property int $followup_evaluation_id
 * @property OutcomePhase $phase
 * @property OutcomeMetric $outcome_metric
 * @property string|null $baseline_value
 * @property string|null $followup_value
 * @property string|null $reference_value
 * @property string|null $absolute_change
 * @property string|null $relative_change_pct
 * @property string|null $point_change
 * @property OutcomeResult $result
 * @property NotEvaluableReason|null $not_evaluable_reason
 * @property bool $comparable
 * @property bool $resolution_condition_met
 * @property array<string, mixed> $evidence
 * @property string|null $manual_note
 * @property Carbon $evaluated_at
 */
class Outcome extends Model
{
    protected $guarded = [];

    /** @var list<string> the only columns that may change after insert */
    private const MUTABLE = ['manual_note', 'manual_note_by', 'manual_note_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'phase' => OutcomePhase::class,
            'outcome_metric' => OutcomeMetric::class,
            'result' => OutcomeResult::class,
            'not_evaluable_reason' => NotEvaluableReason::class,
            'comparable' => 'boolean',
            'resolution_condition_met' => 'boolean',
            'evidence' => 'array',
            'baseline_value' => 'string',
            'followup_value' => 'string',
            'reference_value' => 'string',
            'absolute_change' => 'string',
            'relative_change_pct' => 'string',
            'point_change' => 'string',
            'evaluated_at' => 'datetime',
            'manual_note_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Outcome $outcome): void {
            $illegal = array_diff(array_keys($outcome->getDirty()), self::MUTABLE);

            if ($illegal !== []) {
                throw new LogicException('An outcome is immutable; only its manual note can change.');
            }
        });

        static::deleting(fn () => throw new LogicException('An outcome is never deleted.'));
    }

    /**
     * Owner: the company's outcomes. Manager: the own department's. Staff: none. Uses the scoped issue query.
     *
     * @param  Builder<Outcome>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isStaff()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('outcomes.issue_id', Issue::visibleTo($user)->select('issues.id'));
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<RuleEvaluation, $this> */
    public function baselineEvaluation(): BelongsTo
    {
        return $this->belongsTo(RuleEvaluation::class, 'baseline_evaluation_id');
    }

    /** @return BelongsTo<RuleEvaluation, $this> */
    public function followupEvaluation(): BelongsTo
    {
        return $this->belongsTo(RuleEvaluation::class, 'followup_evaluation_id');
    }

    /** @return HasMany<Feedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
