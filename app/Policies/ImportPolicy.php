<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Imports belong to a department. An active Owner acts on every import of the own company, an active Manager
 * only on imports of the own department. Staff and inactive users are denied everything.
 */
class ImportPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Import $import): bool
    {
        return $this->canAct($user, $import);
    }

    public function create(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function confirm(User $user, Import $import): bool
    {
        return $this->canAct($user, $import);
    }

    public function cancel(User $user, Import $import): bool
    {
        return $this->canAct($user, $import);
    }

    public function retry(User $user, Import $import): bool
    {
        return $this->canAct($user, $import);
    }

    public function download(User $user, Import $import): bool
    {
        return $this->canAct($user, $import);
    }

    private function canAct(User $user, Import $import): bool
    {
        if ($this->isActiveOwner($user)) {
            return $user->company_id === $import->company_id;
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $import->department_id);
    }
}
