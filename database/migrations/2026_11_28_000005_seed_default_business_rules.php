<?php

use App\Actions\Intelligence\SeedDefaultRules;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill: every department that already exists gets its three predefined rules. Idempotent and never
 * overwrites an existing rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seed = new SeedDefaultRules;

        Department::query()->orderBy('id')->each(fn (Department $department) => $seed->handle($department));
    }

    public function down(): void
    {
        // The rules belong to the table dropped by the earlier migration's rollback.
    }
};
