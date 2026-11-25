<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->foreignId('rule_evaluation_id')->nullable()->constrained('rule_evaluations')->nullOnDelete();
            $table->string('title', 120);
            $table->string('explanation', 400);
            $table->string('severity');
            $table->string('period_label');
            $table->jsonb('evidence');
            $table->string('source_ref');
            $table->timestamps();

            $table->unique(['brief_id', 'position']);
        });

        DB::statement('ALTER TABLE brief_items ADD CONSTRAINT brief_items_position_check CHECK (position BETWEEN 1 AND 5)');
        DB::statement("ALTER TABLE brief_items ADD CONSTRAINT brief_items_severity_check CHECK (severity IN ('low','medium','high','critical'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_items');
    }
};
