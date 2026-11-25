<?php

namespace App\Policies;

use App\Models\BusinessRule;
use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Read access follows the department. Editing rules is a PoV Polish feature, so no update ability exists yet.
 */
class BusinessRulePolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, BusinessRule $rule): bool
    {
        if ($this->isActiveOwner($user)) {
            return Department::query()->whereKey($rule->department_id)->where('company_id', $user->company_id)->exists();
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $rule->department_id);
    }
}
