<?php

namespace App\Policies;

use App\Models\Brief;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Briefs belong to a company. Active Owners and Managers of that company may view, generate and request audio;
 * Staff never. A Manager's brief is scoped when it is generated (BriefGenerator), not by this policy.
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
        return $this->isActiveOwnerOrManager($user);
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
        return $this->isActiveOwnerOrManager($user) && $user->company_id === $brief->company_id;
    }
}
