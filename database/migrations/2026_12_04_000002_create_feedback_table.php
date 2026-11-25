<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('issue_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('outcome_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('useful');
            $t->string('note', 500)->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'outcome_id']);
            $t->index('issue_id');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE feedback ADD CONSTRAINT feedback_one_target_check CHECK ((issue_id IS NOT NULL) <> (outcome_id IS NOT NULL));
CREATE UNIQUE INDEX feedback_user_issue_unique ON feedback (user_id, issue_id) WHERE outcome_id IS NULL;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS feedback_user_issue_unique');
        Schema::dropIfExists('feedback');
    }
};
