<?php

namespace App\Actions\Platform;

use App\Enums\Role;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;

/** Changes a company user's profile, role and place in the company. The audit trail lists the changed field names. */
final class UpdateCompanyUser
{
    public function __construct(private readonly CompanyUserRules $rules, private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  validated input */
    public function handle(PlatformAdmin $admin, Company $company, User $user, array $data): User
    {
        $role = Role::from((string) $data['role']);
        $this->rules->assertKeepsAnOwner($company, $user, $role === Role::Owner);

        $before = $user->only(['name', 'email', 'role', 'job_title', 'department_id', 'manager_id']);

        $user->fill([
            'name' => trim((string) $data['name']),
            'email' => (string) $data['email'],
            'role' => $role,
            'job_title' => isset($data['job_title']) && trim((string) $data['job_title']) !== '' ? trim((string) $data['job_title']) : null,
            ...$this->rules->placement($company, $role, $data),
        ]);

        $changed = array_keys(array_filter(
            $user->only(['name', 'email', 'role', 'job_title', 'department_id', 'manager_id']),
            fn ($value, $key) => ($before[$key] instanceof Role ? $before[$key]->value : $before[$key]) !== ($value instanceof Role ? $value->value : $value),
            ARRAY_FILTER_USE_BOTH,
        ));

        $user->save();

        if (in_array('role', $changed, true) || in_array('email', $changed, true)) {
            $this->rules->endSessions($user); // the new role or login takes effect immediately
        }

        $changed !== [] && $this->audit->record($admin, 'user.updated', $company->id, ['user_id' => $user->id, 'fields' => $changed]);

        return $user;
    }
}
