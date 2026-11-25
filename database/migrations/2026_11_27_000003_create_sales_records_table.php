<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_id')->constrained()->restrictOnDelete();
            $table->string('branch_code', 20);
            $table->date('sales_date');
            $table->decimal('revenue', 14, 2);
            $table->decimal('target_revenue', 14, 2);
            $table->integer('order_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('superseded_by_import_id')->nullable()->constrained('imports')->restrictOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'sales_date']);
            $table->index('import_id');
        });

        // At most one active record per business identity, however many imports overlap.
        DB::statement('CREATE UNIQUE INDEX sales_records_active_identity_unique ON sales_records (department_id, branch_code, sales_date) WHERE is_active');
        DB::statement('ALTER TABLE sales_records ADD CONSTRAINT sales_records_revenue_non_negative CHECK (revenue >= 0)');
        DB::statement('ALTER TABLE sales_records ADD CONSTRAINT sales_records_target_revenue_non_negative CHECK (target_revenue >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_records');
    }
};
