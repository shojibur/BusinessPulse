<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('task_draft_id')->nullable()->unique()->constrained('task_drafts')->restrictOnDelete();
            $t->foreignId('meeting_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('issue_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('title', 150);
            $t->text('description')->default('');
            $t->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $t->date('due_date');
            $t->string('status', 20)->default('pending');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['assignee_id', 'status']);
            $t->index('issue_id');
            $t->index('due_date');
            $t->index(['department_id', 'status']);
        });

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_status_check CHECK (status IN ('pending','in_progress','blocked','completed','cancelled'))");
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_completed_at_check CHECK ((status = 'completed') = (completed_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
