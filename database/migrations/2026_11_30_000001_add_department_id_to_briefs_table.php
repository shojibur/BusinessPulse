<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S05 correction: a Manager's brief is scoped to the Manager's department. Null means a company-wide (Owner) brief.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('company_id')->constrained();
            $table->index(['company_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'department_id']);
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
