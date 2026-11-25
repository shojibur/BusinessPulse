<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the fictional demonstration identity. Refuses to run unless DEMO_MODE=true.
     */
    public function run(): void
    {
        $this->call(DemoIdentitySeeder::class);
    }
}
