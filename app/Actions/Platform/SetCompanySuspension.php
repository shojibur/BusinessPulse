<?php

namespace App\Actions\Platform;

use App\Models\Company;
use App\Models\PlatformAdmin;

/**
 * Suspends or reactivates a company. Company users are rejected at sign-in and on their next request while suspended.
 * Idempotent: repeating the same action changes nothing and writes no audit row.
 */
final class SetCompanySuspension
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $admin, Company $company, bool $suspend): bool
    {
        if ($company->isSuspended() === $suspend) {
            return false;
        }

        $company->forceFill(['suspended_at' => $suspend ? now() : null])->save();
        $this->audit->record($admin, $suspend ? 'company.suspended' : 'company.reactivated', $company->id);

        return true;
    }
}
