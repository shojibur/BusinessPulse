<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $t->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $t->string('suggested_title', 150);
            $t->text('suggested_description')->default('');
            $t->string('suggested_assignee_name', 150)->nullable();
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('assignee_resolution', 10)->default('none');
            $t->string('suggested_due_date_text')->nullable();
            $t->date('suggested_due_date')->nullable();
            $t->date('due_date')->nullable();
            $t->string('deadline_resolution', 10)->default('none');
            $t->text('transcript_excerpt');
            $t->string('status', 12)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            // The foreign key to tasks is added by S07 (A3): the tasks table does not exist yet.
            $t->unsignedBigInteger('task_id')->nullable()->unique();
            $t->unsignedInteger('extraction_run');
            $t->timestamps();

            $t->index(['meeting_id', 'status']);
        });

        DB::statement("ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_status_check CHECK (status IN ('pending','approved','rejected','superseded'))");
        DB::statement("ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_assignee_resolution_check CHECK (assignee_resolution IN ('resolved','ambiguous','unmatched','none'))");
        DB::statement("ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_deadline_resolution_check CHECK (deadline_resolution IN ('resolved','ambiguous','none'))");
        DB::statement("ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_assignee_resolved_check CHECK (assignee_resolution <> 'resolved' OR assignee_id IS NOT NULL)");
        DB::statement("ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_deadline_resolved_check CHECK (deadline_resolution <> 'resolved' OR due_date IS NOT NULL)");
        DB::statement('ALTER TABLE task_drafts ADD CONSTRAINT task_drafts_run_check CHECK (extraction_run >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_drafts');
    }
};
