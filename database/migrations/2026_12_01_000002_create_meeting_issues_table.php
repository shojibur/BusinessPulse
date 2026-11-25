<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_issues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $t->foreignId('issue_id')->constrained()->restrictOnDelete();
            $t->unique(['meeting_id', 'issue_id']);
            $t->index('issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_issues');
    }
};
