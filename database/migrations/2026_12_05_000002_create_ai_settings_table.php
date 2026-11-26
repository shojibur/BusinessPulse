<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S11: site-wide AI provider settings edited by a platform admin. One row. API keys are stored encrypted (the model
 * casts them with the application key) and are never shown again. An empty value falls back to the `.env` setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('text_provider', 20)->nullable();
            $table->string('text_base_url')->nullable();
            $table->text('text_api_key')->nullable();
            $table->string('text_model', 100)->nullable();
            $table->string('transcription_base_url')->nullable();
            $table->text('transcription_api_key')->nullable();
            $table->string('transcription_model', 100)->nullable();
            $table->string('speech_base_url')->nullable();
            $table->text('speech_api_key')->nullable();
            $table->string('speech_model', 100)->nullable();
            $table->string('speech_voice', 50)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
