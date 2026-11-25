<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\SalesRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Sales records are read-only for everyone: there is deliberately no create, update or delete ability.
 */
class SalesRecordPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, SalesRecord $record): bool
    {
        if ($this->isActiveOwner($user)) {
            return Department::query()->whereKey($record->department_id)->where('company_id', $user->company_id)->exists();
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $record->department_id);
    }
}
