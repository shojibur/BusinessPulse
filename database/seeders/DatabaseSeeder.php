<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed` (and `db:seed`) leave the demo company populated, as `php artisan demo:seed
     * --populated` does: the fictional company and users, the demo platform admin, the week 7 history and the week 8
     * decline imported and evaluated by the real pipeline (open high issue, urgent notifications), a draft meeting,
     * and the demo clock. (`demo:seed` or `demo:reset --force` give the clean presenter start instead.) It stays inside the demo guard: outside DEMO_MODE, or next to a real company, it
     * prints why it refused and writes nothing.
     */
    public function run(): void
    {
        $this->command->call('demo:seed', ['--populated' => true]);
    }
}
