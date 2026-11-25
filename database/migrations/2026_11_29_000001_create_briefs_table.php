<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->string('status')->default('queued');
            $table->string('source')->nullable();
            $table->date('as_of_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('coverage_status')->nullable();
            $table->text('headline')->nullable();
            $table->text('summary_text')->nullable();
            $table->jsonb('input_snapshot')->nullable();
            $table->string('model')->nullable();
            $table->text('error_message')->nullable();
            $table->string('audio_status')->default('none');
            $table->string('audio_path')->nullable();
            $table->string('audio_model')->nullable();
            $table->string('audio_voice')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['company_id', 'status']);
        });

        DB::statement("ALTER TABLE briefs ADD CONSTRAINT briefs_status_check CHECK (status IN ('queued','generating','completed','failed'))");
        DB::statement("ALTER TABLE briefs ADD CONSTRAINT briefs_source_check CHECK (source IS NULL OR source IN ('ai','fallback'))");
        DB::statement("ALTER TABLE briefs ADD CONSTRAINT briefs_coverage_check CHECK (coverage_status IS NULL OR coverage_status IN ('sufficient','partial','stale'))");
        DB::statement("ALTER TABLE briefs ADD CONSTRAINT briefs_audio_status_check CHECK (audio_status IN ('none','queued','generating','ready','failed'))");
        DB::statement('ALTER TABLE briefs ADD CONSTRAINT briefs_period_pair_check CHECK ((period_start IS NULL) = (period_end IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('briefs');
    }
};
