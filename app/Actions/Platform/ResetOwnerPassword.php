<?php

namespace App\Actions\Platform;

use App\Enums\Role;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sets a new password for the company's Owner and ends the Owner's open sessions. The password is never logged and is
 * not part of the audit properties.
 */
final class ResetOwnerPassword
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $admin, Company $company, string $password): ?User
    {
        $owner = User::query()->where('company_id', $company->id)->where('role', Role::Owner->value)->orderBy('id')->first();

        if ($owner === null) {
            return null;
        }

        $owner->forceFill(['password' => $password, 'remember_token' => null])->save();

        // Database-stored sessions of the Owner end now; a no-op for other drivers (their sessions expire naturally).
        $table = (string) config('session.table', 'sessions');

        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $owner->id)->delete();
        }

        $this->audit->record($admin, 'company.owner_password_reset', $company->id, ['owner_id' => $owner->id]);

        return $owner;
    }
}
