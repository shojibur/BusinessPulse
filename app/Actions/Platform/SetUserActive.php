<?php

namespace App\Actions\Platform;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;

/** Deactivates (and signs out) or reactivates a company user. Nothing is deleted. Idempotent. */
final class SetUserActive
{
    public function __construct(private readonly CompanyUserRules $rules, private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $admin, Company $company, User $user, bool $active): bool
    {
        if ($user->is_active === $active) {
            return false;
        }

        $this->rules->assertKeepsAnOwner($company, $user, $active);

        $user->forceFill(['is_active' => $active])->save();
        $active || $this->rules->endSessions($user);

        $this->audit->record($admin, $active ? 'user.reactivated' : 'user.deactivated', $company->id, ['user_id' => $user->id]);

        return true;
    }
}
