<?php

namespace App\Models;

use App\Enums\RuleKey;
use App\Models\Concerns\ScopedThroughDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A predefined rule of a department. Money-like values are decimal strings; params are decimal strings too.
 *
 * @property int $id
 * @property int $department_id
 * @property RuleKey $key
 * @property string $name
 * @property string|null $threshold
 * @property string $severity
 * @property bool $is_active
 * @property array<string, string> $params
 */
class BusinessRule extends Model
{
    use ScopedThroughDepartment;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'key' => RuleKey::class,
            'threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'params' => 'array',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<RuleEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(RuleEvaluation::class);
    }
}
