<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outcomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('issue_id')->constrained()->restrictOnDelete();
            $t->foreignId('baseline_evaluation_id')->constrained('rule_evaluations')->restrictOnDelete();
            $t->foreignId('followup_evaluation_id')->constrained('rule_evaluations')->restrictOnDelete();
            $t->string('phase');
            $t->string('outcome_metric');
            $t->decimal('baseline_value', 14, 2)->nullable();
            $t->decimal('followup_value', 14, 2)->nullable();
            $t->decimal('reference_value', 14, 2)->nullable();
            $t->decimal('absolute_change', 14, 2)->nullable();
            $t->decimal('relative_change_pct', 8, 2)->nullable();
            $t->decimal('point_change', 8, 2)->nullable();
            $t->string('result');
            $t->string('not_evaluable_reason')->nullable();
            $t->boolean('comparable');
            $t->boolean('resolution_condition_met');
            $t->jsonb('evidence');
            $t->text('manual_note')->nullable();
            $t->foreignId('manual_note_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('manual_note_at')->nullable();
            $t->timestamp('evaluated_at');
            $t->timestamps();

            $t->unique(['issue_id', 'followup_evaluation_id']);
            $t->index(['issue_id', 'evaluated_at']);
            $t->index('followup_evaluation_id');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE outcomes
  ADD CONSTRAINT outcomes_phase_check  CHECK (phase IN ('pre_action','during_action','post_action')),
  ADD CONSTRAINT outcomes_metric_check CHECK (outcome_metric IN ('weekly_revenue','target_achievement','coverage')),
  ADD CONSTRAINT outcomes_result_check CHECK (result IN ('improved','unchanged','worsened','not_evaluable')),
  ADD CONSTRAINT outcomes_reason_values_check CHECK (not_evaluable_reason IS NULL OR not_evaluable_reason IN
      ('followup_not_after_baseline','followup_period_partial','branches_changed','baseline_insufficient_data','followup_insufficient_data','zero_baseline_value')),
  ADD CONSTRAINT outcomes_reason_presence_check CHECK ((result = 'not_evaluable') = (not_evaluable_reason IS NOT NULL)),
  ADD CONSTRAINT outcomes_comparable_check CHECK (comparable = (result <> 'not_evaluable')),
  ADD CONSTRAINT outcomes_resolution_check CHECK (NOT resolution_condition_met OR (comparable AND phase = 'post_action')),
  ADD CONSTRAINT outcomes_values_check CHECK (
      (result = 'not_evaluable'
        AND baseline_value IS NULL AND followup_value IS NULL AND reference_value IS NULL
        AND absolute_change IS NULL AND relative_change_pct IS NULL AND point_change IS NULL)
      OR
      (result <> 'not_evaluable' AND baseline_value IS NOT NULL AND followup_value IS NOT NULL AND (
          (outcome_metric = 'weekly_revenue' AND reference_value IS NOT NULL AND absolute_change IS NOT NULL
             AND relative_change_pct IS NOT NULL AND point_change IS NULL)
          OR
          (outcome_metric <> 'weekly_revenue' AND point_change IS NOT NULL AND reference_value IS NULL
             AND absolute_change IS NULL AND relative_change_pct IS NULL)))),
  ADD CONSTRAINT outcomes_note_check CHECK (manual_note IS NULL OR char_length(manual_note) BETWEEN 1 AND 1000)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outcomes');
    }
};
