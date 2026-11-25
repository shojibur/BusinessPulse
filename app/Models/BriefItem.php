<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finding of a brief. `evidence` is a value copy of IssueEvidence at generation time and never changes.
 *
 * @property int $id
 * @property int $brief_id
 * @property int $position
 * @property int|null $issue_id
 * @property int|null $rule_evaluation_id
 * @property string $title
 * @property string $explanation
 * @property string $severity
 * @property string $period_label
 * @property array<string, mixed> $evidence
 * @property string $source_ref
 */
class BriefItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    /** @return BelongsTo<Brief, $this> */
    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
