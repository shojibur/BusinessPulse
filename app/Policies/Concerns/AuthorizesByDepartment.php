<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Shared policy helpers. Policy conventions (docs/architecture.md): one policy per model, default
 * deny, deny every action to an inactive user, and no blanket Owner `before()` hook - each method
 * states explicitly who may act.
 */
trait AuthorizesByDepartment
{
    /**
     * True when the user belongs to the given department. A user without a department never matches.
     */
    protected function sameDepartment(User $user, ?int $departmentId): bool
    {
        return $user->department_id !== null
            && $departmentId !== null
            && $user->department_id === $departmentId;
    }

    protected function isActiveOwner(User $user): bool
    {
        return $user->is_active && $user->isOwner();
    }

    protected function isActiveOwnerOrManager(User $user): bool
    {
        return $user->is_active && ($user->isOwner() || $user->isManager());
    }
}
