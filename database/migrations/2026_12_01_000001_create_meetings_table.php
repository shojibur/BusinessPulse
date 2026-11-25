<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('title', 150);
            $t->date('meeting_date');
            $t->string('recording_path')->nullable();
            $t->string('recording_original_name')->nullable();
            $t->string('recording_mime', 100)->nullable();
            $t->unsignedBigInteger('recording_size')->nullable();
            $t->timestamp('recording_uploaded_at')->nullable();
            $t->timestamp('recording_consent_at')->nullable();
            $t->foreignId('recording_consent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('recording_deleted_at')->nullable();
            $t->text('transcript')->nullable();
            $t->string('transcript_source', 10)->nullable();
            $t->timestamp('transcript_edited_at')->nullable();
            $t->jsonb('decisions')->nullable();
            $t->string('status', 20)->default('draft');
            $t->string('failure_stage', 20)->nullable();
            $t->string('error_message', 100)->nullable();
            $t->unsignedInteger('extraction_discarded_count')->default(0);
            $t->timestamp('extracted_at')->nullable();
            $t->timestamps();

            $t->index(['department_id', 'meeting_date']);
            $t->index('status');
        });

        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_status_check CHECK (status IN ('draft','uploaded','transcribing','transcribed','extracting','ready_for_review','failed'))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_transcript_source_check CHECK (transcript_source IS NULL OR transcript_source IN ('audio','manual'))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_failure_stage_check CHECK (failure_stage IS NULL OR failure_stage IN ('transcription','extraction'))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_failed_stage_pair_check CHECK ((status = 'failed') = (failure_stage IS NOT NULL))");
        DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_decisions_array_check CHECK (decisions IS NULL OR jsonb_typeof(decisions) = 'array')");
        DB::statement('ALTER TABLE meetings ADD CONSTRAINT meetings_recording_deleted_check CHECK (recording_path IS NULL OR recording_deleted_at IS NULL)');
        DB::statement('ALTER TABLE meetings ADD CONSTRAINT meetings_consent_pair_check CHECK ((recording_consent_at IS NULL) = (recording_consent_by IS NULL))');
        DB::statement('CREATE INDEX meetings_purge_idx ON meetings (recording_uploaded_at) WHERE recording_path IS NOT NULL AND recording_deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
