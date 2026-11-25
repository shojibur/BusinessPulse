<?php

namespace App\Models\Concerns;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Department scoping for models that have `department_id` but NO `company_id` column (sales records, rules,
 * evaluations, issues). ScopedToDepartment cannot be used for them because it filters the Owner by `company_id`.
 *
 * Inactive user => nothing; Owner => rows of the departments of the user's company; user without a
 * department => nothing; otherwise only rows of the own department. Call `visibleTo($user)` explicitly.
 */
trait ScopedThroughDepartment
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isOwner()) {
            return $query->whereIn(
                $query->qualifyColumn('department_id'),
                Department::query()->where('company_id', $user->company_id)->select('id'),
            );
        }

        if ($user->department_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->qualifyColumn('department_id'), $user->department_id);
    }
}
