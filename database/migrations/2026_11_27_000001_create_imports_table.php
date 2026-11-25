<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            // Private disk path, never exposed to the frontend. Empty until the file is stored.
            $table->string('stored_path')->default('');
            $table->char('file_hash', 64)->nullable();
            $table->string('status');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('corrected_rows')->default(0);
            $table->unsignedInteger('unchanged_rows')->default(0);
            $table->date('min_date')->nullable();
            $table->date('max_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index('file_hash');
        });

        DB::statement("ALTER TABLE imports ADD CONSTRAINT imports_status_check CHECK (status IN ('uploaded','validated','processing','completed','completed_with_errors','failed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
