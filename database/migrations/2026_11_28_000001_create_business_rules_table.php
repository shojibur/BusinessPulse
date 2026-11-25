<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->decimal('threshold', 8, 2)->nullable();
            $table->string('severity');
            $table->boolean('is_active')->default(true);
            $table->jsonb('params')->default('{}');
            $table->timestamps();

            $table->unique(['department_id', 'key']);
        });

        DB::statement("ALTER TABLE business_rules ADD CONSTRAINT business_rules_key_check CHECK (key IN ('weekly_revenue_decline','sales_target_shortfall','missing_sales_data'))");
        DB::statement("ALTER TABLE business_rules ADD CONSTRAINT business_rules_severity_check CHECK (severity IN ('low','medium','high','critical'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rules');
    }
};
