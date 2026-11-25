<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

class CompanyPolicy
{
    use AuthorizesByDepartment;

    public function view(User $user, Company $company): bool
    {
        return $this->isActiveOwnerOrManager($user) && $user->company_id === $company->id;
    }

    public function update(User $user, Company $company): bool
    {
        return $this->isActiveOwner($user) && $user->company_id === $company->id;
    }
}
