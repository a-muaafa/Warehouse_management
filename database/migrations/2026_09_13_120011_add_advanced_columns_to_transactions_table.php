<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('user_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('item_batch_id')->nullable()->after('warehouse_id')->constrained('item_batches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('item_batch_id')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('customer_id')->constrained('units')->nullOnDelete();
            $table->string('movement_reason')->nullable()->after('type'); // purchase, sale_delivery, transfer_in, transfer_out, customer_return, supplier_return, damaged, audit_adjustment
            $table->decimal('unit_price', 12, 2)->default(0)->after('quantity');
            $table->decimal('total_amount', 12, 2)->default(0)->after('unit_price');
            $table->string('reference_doc')->nullable()->after('total_amount'); // PO#, Delivery Note#, Invoice#
            $table->foreignId('approved_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->enum('status', ['completed', 'pending_approval', 'cancelled'])->default('completed')->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['item_batch_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'warehouse_id',
                'item_batch_id',
                'supplier_id',
                'customer_id',
                'unit_id',
                'movement_reason',
                'unit_price',
                'total_amount',
                'reference_doc',
                'approved_by',
                'status',
            ]);
        });
    }
};
