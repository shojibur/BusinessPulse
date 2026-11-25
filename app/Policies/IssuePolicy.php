<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Issue;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Issues belong to a department. Owner: company-wide. Manager: own department. Staff: no access.
 */
class IssuePolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $this->canAct($user, $issue->department_id);
    }

    /** Acknowledge, manual monitoring and manual resolve. */
    public function transition(User $user, Issue $issue): bool
    {
        return $this->canAct($user, $issue->department_id);
    }

    /** Refresh intelligence for a department. */
    public function refresh(User $user, Department $department): bool
    {
        return $this->canAct($user, $department->id, $department->company_id);
    }

    private function canAct(User $user, int $departmentId, ?int $companyId = null): bool
    {
        if ($this->isActiveOwner($user)) {
            return $companyId === null
                ? Department::query()->whereKey($departmentId)->where('company_id', $user->company_id)->exists()
                : $user->company_id === $companyId;
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $departmentId);
    }
}
