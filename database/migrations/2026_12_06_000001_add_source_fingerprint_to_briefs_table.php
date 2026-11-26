<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A fingerprint of the data a brief was requested from, so an unchanged request reuses the existing brief. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function (Blueprint $table): void {
            $table->string('source_fingerprint', 64)->nullable();
            $table->index(['company_id', 'source_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('briefs', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'source_fingerprint']);
            $table->dropColumn('source_fingerprint');
        });
    }
};
