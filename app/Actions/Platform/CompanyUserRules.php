<?php

namespace App\Actions\Platform;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The shared rules of the platform's user management: who may report to whom, and "a company always keeps at least
 * one active Owner". Owners have no department and no manager; a Manager belongs to a department and reports to an
 * Owner; Staff belong to a department and report to a Manager of that department.
 */
final class CompanyUserRules
{
    /**
     * Department and manager for a role, from validated input.
     *
     * @param  array<string, mixed>  $input
     * @return array{department_id: int|null, manager_id: int|null}
     */
    public function placement(Company $company, Role $role, array $input): array
    {
        return match ($role) {
            Role::Owner => ['department_id' => null, 'manager_id' => null],
            Role::Manager => [
                'department_id' => (int) $input['department_id'],
                'manager_id' => User::query()->where('company_id', $company->id)->where('role', Role::Owner->value)->where('is_active', true)->orderBy('id')->value('id'),
            ],
            Role::Staff => ['department_id' => (int) $input['department_id'], 'manager_id' => (int) $input['manager_id']],
        };
    }

    /** @throws ValidationException when this change would leave the company without an active Owner */
    public function assertKeepsAnOwner(Company $company, User $user, bool $stillActiveOwner): void
    {
        if ($stillActiveOwner || ! $user->isOwner() || ! $user->is_active) {
            return;
        }

        $others = User::query()->where('company_id', $company->id)->where('role', Role::Owner->value)->where('is_active', true)->whereKeyNot($user->id)->exists();

        if (! $others) {
            throw ValidationException::withMessages(['user' => 'A company needs at least one active Owner. Add or activate another Owner first.']);
        }
    }

    /** Ends the user's stored sessions now (a no-op for session drivers that keep no table). */
    public function endSessions(User $user): void
    {
        $table = (string) config('session.table', 'sessions');

        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $user->id)->delete();
        }
    }
}
