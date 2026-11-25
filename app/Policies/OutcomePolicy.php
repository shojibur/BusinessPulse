<?php

namespace App\Policies;

use App\Models\Outcome;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/** Outcomes are management information: Owner (company) and Manager (own department). Staff never. */
class OutcomePolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Outcome $outcome): bool
    {
        return $this->viewAny($user) && Outcome::query()->visibleTo($user)->whereKey($outcome->id)->exists();
    }
}
