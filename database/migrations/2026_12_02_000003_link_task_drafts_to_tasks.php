<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_drafts', function (Blueprint $t) {
            $t->string('rejection_reason', 500)->nullable();
            $t->foreign('task_id')->references('id')->on('tasks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_drafts', function (Blueprint $t) {
            $t->dropForeign(['task_id']); // must precede dropping tasks
            $t->dropColumn('rejection_reason');
        });
    }
};
