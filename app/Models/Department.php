<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $settings
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /**
     * The branch codes this department is expected to report (docs/stage-1-refinements.md R2).
     *
     * @return list<string>
     */
    public function expectedBranches(): array
    {
        return array_values($this->settings['expected_branches'] ?? []);
    }

    /**
     * Departments the user may see: the Owner sees every department of the company, others only
     * their own, inactive users and users without a department see none. (A department has no
     * `department_id` column, so it cannot use ScopedToDepartment.)
     *
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isOwner()) {
            return $query->where('company_id', $user->company_id);
        }

        if ($user->department_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($user->department_id);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
