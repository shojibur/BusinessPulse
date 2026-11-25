<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->foreignId('issue_id')->nullable()->constrained()->restrictOnDelete();
            $t->jsonb('properties')->default('{}');
            $t->timestamp('created_at');

            $t->index(['issue_id', 'created_at']);
            $t->index(['subject_type', 'subject_id']);
            $t->index(['company_id', 'department_id', 'created_at']);
        });

        DB::statement("ALTER TABLE activity_logs ADD CONSTRAINT activity_logs_subject_type_check CHECK (subject_type IN ('Import','RuleEvaluation','BusinessRule','Issue','Brief','Meeting','TaskDraft','Task','Outcome'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
