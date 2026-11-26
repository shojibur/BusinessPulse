<?php

namespace App\Actions\Platform;

use App\Enums\Role;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;

/** Adds a user to a company. The password is hashed by the model and never audited. */
final class CreateCompanyUser
{
    public function __construct(private readonly CompanyUserRules $rules, private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  validated input */
    public function handle(PlatformAdmin $admin, Company $company, array $data): User
    {
        $role = Role::from((string) $data['role']);

        $user = new User([
            'name' => trim((string) $data['name']),
            'email' => (string) $data['email'],
            'password' => (string) $data['password'],
            'role' => $role,
            'job_title' => isset($data['job_title']) && trim((string) $data['job_title']) !== '' ? trim((string) $data['job_title']) : null,
            ...$this->rules->placement($company, $role, $data),
        ]);
        $user->company_id = $company->id;
        $user->is_active = true;
        $user->save();

        $this->audit->record($admin, 'user.created', $company->id, ['user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }
}
