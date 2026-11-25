<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Department scoping for any model with `company_id` and `department_id` columns.
 *
 * Call `Model::visibleTo($user)` explicitly in controllers, policies and route bindings; it is an
 * opt-in local scope, never a global scope. A model without a direct `company_id` column must
 * override the Owner branch (for example with `whereHas('department', ...)`).
 *
 * Rule: inactive user => nothing; Owner => the company's rows; user without a department => nothing;
 * otherwise only rows of the user's own department.
 */
trait ScopedToDepartment
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
            return $query->where($query->qualifyColumn('company_id'), $user->company_id);
        }

        if ($user->department_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->qualifyColumn('department_id'), $user->department_id);
    }
}
