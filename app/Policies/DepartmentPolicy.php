<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

class DepartmentPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Department $department): bool
    {
        if ($this->isActiveOwner($user)) {
            return $user->company_id === $department->company_id;
        }

        return $user->is_active
            && $user->isManager()
            && $this->sameDepartment($user, $department->id);
    }
}
