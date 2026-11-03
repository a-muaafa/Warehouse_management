<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Transaction-to-Batch pivot table (Section 4.3)
        Schema::create('transaction_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('item_batch_id')->constrained('item_batches')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['transaction_id', 'item_batch_id']);
        });

        // 2. Central Immutable Inventory Movement Ledger (Section 3)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->nullable()->index();
            $table->string('movement_type', 50)->index(); // receipt, issue, transfer_out, transfer_in, audit_adjustment, discrepancy_write_off
            $table->string('source_type', 100)->nullable(); // App\Models\Transaction, App\Models\WarehouseTransfer, App\Models\InventoryAudit
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->decimal('quantity_before', 15, 4)->default(0);
            $table->decimal('quantity_change', 15, 4); // +50, -10, etc.
            $table->decimal('quantity_after', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        // 3. Upgrade items table: decimal quantities, softDeletes, created_by/updated_by, indexes
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->default(0)->change();
            $table->decimal('min_alert_level', 15, 4)->default(5)->change();
            $table->decimal('reorder_point', 15, 4)->default(5)->change();
            $table->decimal('safety_stock', 15, 4)->default(2)->change();
            $table->decimal('purchase_price', 15, 4)->default(0)->change();
            $table->decimal('selling_price', 15, 4)->default(0)->change();
            $table->string('valuation_method', 30)->default('fifo')->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->unique('sku');
            $table->index(['department_id', 'category_id']);
            $table->index('name');
        });

        // 4. Upgrade item_batches table: decimal precision, received_at, transaction_id, softDeletes, created_by/updated_by
        Schema::table('item_batches', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('supplier_id')->constrained('transactions')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('expiry_date');
            $table->decimal('purchase_cost', 15, 4)->default(0)->change();
            $table->decimal('selling_price', 15, 4)->default(0)->change();
            $table->decimal('quantity_received', 15, 4)->default(0)->change();
            $table->decimal('quantity_remaining', 15, 4)->default(0)->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['warehouse_id', 'item_id', 'status']);
            $table->index('batch_number');
        });

        // 5. Upgrade transactions table: decimal quantities, idempotency_key, softDeletes, created_by/updated_by, indexes
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('unit_price', 15, 4)->default(0)->change();
            $table->decimal('total_amount', 15, 4)->default(0)->change();
            $table->string('idempotency_key', 100)->nullable()->unique()->after('reference_doc');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['warehouse_id', 'type', 'created_at']);
            $table->index(['item_id', 'type']);
            $table->index('reference_doc');
        });

        // 6. Upgrade warehouse_transfers & transfer_items: full status lifecycle, approval, discrepancy fields, decimal quantities
        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->string('status', 40)->default('pending')->change(); // draft, pending, approved, in_transit, partially_received, completed, discrepancy, rejected, cancelled
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('received_at');
            $table->text('discrepancy_notes')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['from_warehouse_id', 'status']);
            $table->index(['to_warehouse_id', 'status']);
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('received_quantity', 15, 4)->default(0)->change();
            $table->decimal('missing_quantity', 15, 4)->default(0)->after('received_quantity');
            $table->decimal('unit_cost', 15, 4)->default(0)->change();
            $table->string('discrepancy_reason')->nullable()->after('unit_cost');
        });

        // 7. Upgrade inventory_audits & inventory_audit_items: decimal quantities, softDeletes, created_by/updated_by
        Schema::table('inventory_audits', function (Blueprint $table) {
            $table->string('status', 40)->default('draft')->change();
            $table->boolean('freeze_movements')->default(false)->after('status');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::table('inventory_audit_items', function (Blueprint $table) {
            $table->decimal('book_quantity', 15, 4)->default(0)->change();
            $table->decimal('counted_quantity', 15, 4)->default(0)->change();
            $table->decimal('discrepancy_quantity', 15, 4)->default(0)->change();
            $table->decimal('unit_cost', 15, 4)->default(0)->change();
            $table->decimal('discrepancy_value', 15, 4)->default(0)->change();
            $table->string('discrepancy_reason')->nullable()->after('status');
        });

        // 8. SoftDeletes & created_by/updated_by on Warehouses, Suppliers, Customers, Units, Categories
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        // 9. Upgrade activity_logs for rich audit logging (old_values, new_values, subject_type, subject_id)
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('action');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->json('old_values')->nullable()->after('description');
            $table->json('new_values')->nullable()->after('old_values');

            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('transaction_batch_items');
    }
};
