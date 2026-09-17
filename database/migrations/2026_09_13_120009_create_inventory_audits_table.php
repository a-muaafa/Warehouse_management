<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number')->unique();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title');
            $table->enum('type', ['cycle_count', 'periodic_full', 'spot_check'])->default('cycle_count');
            $table->enum('status', ['draft', 'in_progress', 'pending_approval', 'reconciled', 'cancelled'])->default('draft');
            $table->foreignId('audited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained('inventory_audits')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->integer('book_quantity')->default(0);
            $table->integer('counted_quantity')->default(0);
            $table->integer('discrepancy_quantity')->default(0); // counted - book
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discrepancy_value', 12, 2)->default(0); // discrepancy_quantity * unit_cost
            $table->enum('status', ['matched', 'surplus', 'shortage'])->default('matched');
            $table->text('reconciliation_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
    }
};
