<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fictional demonstration company and accounts (docs/demo-scenario.md section 1). Idempotent:
 * rows are matched by company name and by email. Runs only when DEMO_MODE=true and DEMO_PASSWORD is a
 * strong value, and never next to a real (non-demo) company. The password is never printed or logged.
 */
class DemoIdentitySeeder extends Seeder
{
    public const COMPANY_NAME = 'Meridian Home Supplies Sdn Bhd';

    public function run(): void
    {
        if (! $this->guardsPass()) {
            return;
        }

        $password = (string) config('demo.password');

        DB::transaction(function () use ($password): void {
            $company = Company::updateOrCreate(
                ['name' => self::COMPANY_NAME],
                [
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'currency' => 'MYR',
                    'trading_days' => [1, 2, 3, 4, 5, 6],
                    'reporting_cutoff_time' => '12:00',
                    'reporting_cutoff_lag_days' => 1,
                    'is_demo' => true,
                ],
            );

            $sales = Department::firstOrNew(['company_id' => $company->id, 'slug' => 'sales']);
            $sales->name = 'Sales';
            // Expected branches are explicit (no auto-fill from imports); other settings keys are kept.
            $sales->settings = [...($sales->settings ?? []), 'expected_branches' => ['KL', 'PG', 'JB']];
            $sales->save();

            $owner = $this->user($company, $password, 'owner@meridian.demo', 'Lim Wei Han', Role::Owner, 'Owner', null, null);
            $manager = $this->user($company, $password, 'aisyah@meridian.demo', 'Nurul Aisyah', Role::Manager, 'Sales Manager', $sales, $owner);

            foreach ([
                ['daniel.tan@meridian.demo', 'Daniel Tan', 'Sales Executive (KL)'],
                ['daniel.lee@meridian.demo', 'Daniel Lee', 'Sales Executive (KL)'],
                ['priya@meridian.demo', 'Priya Nair', 'Sales Executive (PG)'],
                ['farid@meridian.demo', 'Farid Ismail', 'Sales Executive (JB)'],
            ] as [$email, $name, $title]) {
                $this->user($company, $password, $email, $name, Role::Staff, $title, $sales, $manager);
            }
        });

        $this->say('info', 'Demo identity seeded: Meridian Home Supplies Sdn Bhd, Sales department and 6 users.');
    }

    private function user(Company $company, string $password, string $email, string $name, Role $role, string $title, ?Department $department, ?User $manager): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => $password,
            'role' => $role,
            'job_title' => $title,
            'department_id' => $department?->id,
            'manager_id' => $manager?->id,
        ]);
        $user->company_id = $company->id;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function guardsPass(): bool
    {
        if (config('demo.mode') !== true) {
            $this->say('error', 'Refusing to seed: DEMO_MODE must be true. Nothing was written.');

            return false;
        }

        if (strlen((string) config('demo.password')) < 12) {
            $this->say('error', 'Refusing to seed: DEMO_PASSWORD must be set to at least 12 characters. Nothing was written.');

            return false;
        }

        if (Company::query()->where('is_demo', false)->exists()) {
            $this->say('error', 'Refusing to seed: a non-demo company already exists. Nothing was written.');

            return false;
        }

        return true;
    }

    private function say(string $level, string $message): void
    {
        $this->command?->getOutput()->writeln($level === 'error' ? "<error>{$message}</error>" : "<info>{$message}</info>");
    }
}
