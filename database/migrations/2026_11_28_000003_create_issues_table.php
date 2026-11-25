<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('business_rule_id')->constrained();
            $table->string('scope_key')->default('department');
            $table->foreignId('recurrence_of_issue_id')->nullable()->constrained('issues');
            $table->string('title');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->unsignedInteger('detection_count')->default(1);
            $table->date('period_start');
            $table->date('period_end');
            // Never changes after creation.
            $table->foreignId('baseline_evaluation_id')->constrained('rule_evaluations');
            $table->foreignId('latest_evaluation_id')->constrained('rule_evaluations');
            $table->timestamp('action_started_at')->nullable();
            $table->timestamp('monitoring_started_at')->nullable();
            // Append-only entries {from, to, at, by_user_id, reason, trigger}.
            $table->jsonb('status_history')->default('[]');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->string('resolution_type')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });

        // One non-resolved issue per (rule, department, scope); a recurrence is a new row after resolution.
        DB::statement("CREATE UNIQUE INDEX issues_open_identity_unique ON issues (business_rule_id, department_id, scope_key) WHERE status <> 'resolved'");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_status_check CHECK (status IN ('open','acknowledged','action_in_progress','monitoring','resolved'))");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_severity_check CHECK (severity IN ('low','medium','high','critical'))");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_resolution_type_check CHECK (resolution_type IS NULL OR resolution_type IN ('system_verified','manual'))");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_resolved_presence_check CHECK ((status = 'resolved') = (resolved_at IS NOT NULL))");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_resolution_type_presence_check CHECK ((status = 'resolved') = (resolution_type IS NOT NULL))");
        DB::statement("ALTER TABLE issues ADD CONSTRAINT issues_manual_note_check CHECK (resolution_type IS DISTINCT FROM 'manual' OR resolution_note IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS issues_open_identity_unique');
        Schema::dropIfExists('issues');
    }
};
