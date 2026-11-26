<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('platform:create-admin {--name= : Full name} {--email= : Email address} {--password= : Password (prefer the hidden prompt)}')]
#[Description('Create a platform admin who manages companies at /platform (S10)')]
class PlatformCreateAdmin extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email address', required: true);
        $password = $this->option('password') ?: password('Password (at least 12 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => mb_strtolower(trim($email)), 'password' => $password],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255', 'unique:platform_admins,email'],
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

        PlatformAdmin::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);

        $this->components->info("Created platform admin {$email}. Sign in at /platform/login.");

        return self::SUCCESS;
    }
}
