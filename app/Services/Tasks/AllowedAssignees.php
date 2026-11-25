<?php

namespace App\Services\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** The one definition of who can be assigned: active staff and managers of the task's department. Never the Owner. */
final class AllowedAssignees
{
    /** @return Builder<User> */
    public static function query(int $departmentId): Builder
    {
        return User::query()->where('department_id', $departmentId)->where('is_active', true)->whereIn('role', ['staff', 'manager']);
    }

    public static function contains(int $departmentId, int $userId): bool
    {
        return self::query($departmentId)->whereKey($userId)->exists();
    }
}
