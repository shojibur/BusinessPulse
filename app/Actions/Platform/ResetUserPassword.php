<?php

namespace App\Actions\Platform;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;

/** Sets a new password and ends the user's open sessions. The password is never logged or audited. */
final class ResetUserPassword
{
    public function __construct(private readonly CompanyUserRules $rules, private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $admin, Company $company, User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        $this->rules->endSessions($user);

        $this->audit->record($admin, 'user.password_reset', $company->id, ['user_id' => $user->id]);
    }
}
