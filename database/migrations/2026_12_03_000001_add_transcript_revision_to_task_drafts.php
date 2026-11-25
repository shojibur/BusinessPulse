<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// S07 correction: a draft remembers the transcript revision (sha1) it was extracted from, so approval can refuse a draft
// from an outdated transcript even when its quote still appears.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_drafts', function (Blueprint $table) {
            $table->string('transcript_revision', 40)->nullable()->after('transcript_excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('task_drafts', function (Blueprint $table) {
            $table->dropColumn('transcript_revision');
        });
    }
};
