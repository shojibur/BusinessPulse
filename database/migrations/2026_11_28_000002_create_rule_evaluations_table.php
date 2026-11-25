<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('import_id')->nullable()->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('comparison_start')->nullable();
            $table->date('comparison_end')->nullable();
            $table->string('metric_name');
            $table->decimal('current_value', 14, 2)->nullable();
            $table->decimal('comparison_value', 14, 2)->nullable();
            $table->decimal('difference_value', 14, 2)->nullable();
            $table->decimal('difference_pct', 8, 4)->nullable();
            $table->string('result');
            $table->string('severity')->nullable();
            $table->string('insufficient_reason')->nullable();
            // Null only when the department has no active record (reason no_records).
            $table->date('as_of_date')->nullable();
            $table->date('last_due_date');
            $table->jsonb('evidence');
            $table->timestamp('evaluated_at');
            $table->char('run_key', 64);
            $table->timestamps();

            $table->index(['department_id', 'evaluated_at']);
            $table->unique(['business_rule_id', 'period_start', 'period_end', 'run_key']);
        });

        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_metric_check CHECK (metric_name IN ('weekly_change_pct','target_achievement_pct','coverage_pct'))");
        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_result_check CHECK (result IN ('breached','not_breached','insufficient_data'))");
        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_reason_check CHECK (insufficient_reason IS NULL OR insufficient_reason IN ('incomplete_period','period_absent','zero_denominator','no_records'))");
        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_severity_check CHECK (severity IS NULL OR severity IN ('low','medium','high','critical'))");
        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_severity_presence_check CHECK ((result = 'breached') = (severity IS NOT NULL))");
        DB::statement("ALTER TABLE rule_evaluations ADD CONSTRAINT rule_evaluations_insufficient_presence_check CHECK ((result = 'insufficient_data') = (insufficient_reason IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_evaluations');
    }
};
