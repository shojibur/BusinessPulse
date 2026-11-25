<?php

namespace App\Policies;

use App\Models\Brief;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Briefs belong to a company and optionally to a department (a Manager's brief). An active Owner may act on every
 * brief of the company; an active Manager only on briefs of the own department; Staff never. Company-wide Owner
 * briefs (no department) are not visible to Managers because they may cite other departments' issues.
 */
class BriefPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Brief $brief): bool
    {
        return $this->sameCompany($user, $brief);
    }

    public function create(User $user): bool
    {
        return $this->isActiveOwner($user) || ($user->is_active && $user->isManager() && $user->department_id !== null);
    }

    public function retry(User $user, Brief $brief): bool
    {
        return $this->sameCompany($user, $brief);
    }

    public function requestAudio(User $user, Brief $brief): bool
    {
        return $this->sameCompany($user, $brief);
    }

    public function viewAudio(User $user, Brief $brief): bool
    {
        return $this->sameCompany($user, $brief);
    }

    private function sameCompany(User $user, Brief $brief): bool
    {
        if ($user->company_id !== $brief->company_id) {
            return false;
        }

        if ($this->isActiveOwner($user)) {
            return true;
        }

        return $user->is_active && $user->isManager()
            && $brief->department_id !== null && $this->sameDepartment($user, $brief->department_id);
    }
}
