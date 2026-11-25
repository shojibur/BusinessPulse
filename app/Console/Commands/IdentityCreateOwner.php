<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('identity:create-owner {--company= : Company name} {--name= : Owner full name} {--email= : Owner email} {--password= : Owner password (prefer the hidden prompt)}')]
#[Description('Create the first company, its Sales department and the Owner account (production setup step)')]
class IdentityCreateOwner extends Command
{
    public function handle(): int
    {
        if (Company::query()->exists()) {
            $this->components->error('A company already exists. Nothing was created.');

            return self::FAILURE;
        }

        $company = $this->option('company') ?: text('Company name', required: true);
        $name = $this->option('name') ?: text('Owner full name', required: true);
        $email = $this->option('email') ?: text('Owner email', required: true);
        $password = $this->option('password') ?: password('Owner password (at least 12 characters)', required: true);

        $validator = Validator::make(
            ['company' => $company, 'name' => $name, 'email' => $email, 'password' => $password],
            [
                'company' => ['required', 'string', 'max:150'],
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            // Messages never include the password value itself.
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }
            $this->components->error('Nothing was created.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($company, $name, $email, $password): void {
            $companyModel = Company::create(['name' => $company]);

            Department::create([
                'company_id' => $companyModel->id,
                'name' => 'Sales',
                'slug' => 'sales',
                'settings' => [],
            ]);

            $owner = new User(['name' => $name, 'email' => $email, 'password' => $password, 'role' => Role::Owner]);
            $owner->company_id = $companyModel->id;
            $owner->save();
        });

        $this->components->info("Created company \"{$company}\", the Sales department and the Owner {$email}.");

        return self::SUCCESS;
    }
}
