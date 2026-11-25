<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identity tables (S02). Replaces the starter-kit users migration: BusinessPulse has one
 * company, departments, and three fixed roles. The `sessions` table (S01, SESSION_DRIVER=database)
 * is created here beside `users` because it references `user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('Asia/Kuala_Lumpur');
            $table->string('currency')->default('MYR');
            $table->jsonb('trading_days')->default('[1,2,3,4,5,6]');
            $table->time('reporting_cutoff_time')->default('12:00');
            $table->smallInteger('reporting_cutoff_lag_days')->default(1);
            $table->boolean('is_demo')->default(false);
            $table->integer('recording_retention_days')->default(30);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('job_title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index('department_id');
            $table->index('manager_id');
        });

        // Belt and braces next to Form Request validation: only the three fixed roles are storable.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('owner', 'manager', 'staff'))");

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');
    }
};
