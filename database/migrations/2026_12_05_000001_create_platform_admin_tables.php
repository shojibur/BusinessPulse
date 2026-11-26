<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10: platform administrators (people who run the platform, never company users), their audit log, and the company
 * suspension timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action', 60);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->jsonb('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('suspended_at');
        });
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_admins');
    }
};
