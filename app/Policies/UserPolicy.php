<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

class UserPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, User $target): bool
    {
        if ($this->isActiveOwner($user)) {
            return $user->company_id === $target->company_id;
        }

        return $user->is_active
            && $user->isManager()
            && $this->sameDepartment($user, $target->department_id);
    }

    public function create(User $user): bool
    {
        return $this->isActiveOwner($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    public function reactivate(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    /**
     * Only the Owner administers accounts, and only within the own company (A3, A4).
     */
    private function manageable(User $user, User $target): bool
    {
        return $this->isActiveOwner($user) && $user->company_id === $target->company_id;
    }
}
