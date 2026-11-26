<?php

namespace App\Actions\Platform;

use App\Actions\Intelligence\SeedDefaultRules;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sets up a new tenant in one transaction: the company (never a demo company), its Sales department with the expected
 * branch codes, the three default rules and the first Owner. A failure leaves nothing behind.
 */
final class CreateCompany
{
    public function __construct(private readonly SeedDefaultRules $rules, private readonly PlatformAudit $audit) {}

    /**
     * @param  array{name: string, timezone: string, trading_days: list<int>, branches: list<string>, owner_name: string, owner_email: string, owner_password: string}  $data
     */
    public function handle(PlatformAdmin $admin, array $data): Company
    {
        return DB::transaction(function () use ($admin, $data): Company {
            $company = Company::query()->create([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'trading_days' => $data['trading_days'],
                'is_demo' => false,
            ]);

            $department = Department::query()->create([
                'company_id' => $company->id,
                'name' => 'Sales',
                'slug' => 'sales',
                'settings' => ['expected_branches' => $data['branches']],
            ]);

            $this->rules->handle($department);

            $owner = new User(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => $data['owner_password'], 'role' => Role::Owner]);
            $owner->company_id = $company->id;
            $owner->save();

            $this->audit->record($admin, 'company.created', $company->id, [
                'name' => $company->name,
                'timezone' => $company->timezone,
                'owner_email' => $owner->email,
                'branches' => count($data['branches']),
            ]);

            return $company;
        });
    }
}
