<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\RuleEvaluation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Evaluations are read-only evidence. No create, update or delete ability exists for any user.
 */
class RuleEvaluationPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, RuleEvaluation $evaluation): bool
    {
        if ($this->isActiveOwner($user)) {
            return Department::query()->whereKey($evaluation->department_id)->where('company_id', $user->company_id)->exists();
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $evaluation->department_id);
    }
}
