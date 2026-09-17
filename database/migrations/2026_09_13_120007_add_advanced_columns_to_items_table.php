<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('department_id')->constrained('item_categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('unit')->constrained('units')->nullOnDelete();
            $table->foreignId('secondary_unit_id')->nullable()->after('unit_id')->constrained('units')->nullOnDelete();
            $table->string('sku')->nullable()->after('barcode');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('quantity');
            $table->decimal('selling_price', 12, 2)->default(0)->after('purchase_price');
            $table->enum('valuation_method', ['fifo', 'lifo', 'avg'])->default('fifo')->after('selling_price');
            $table->integer('reorder_point')->default(5)->after('min_alert_level');
            $table->integer('safety_stock')->default(2)->after('reorder_point');
            $table->decimal('storage_cost_rate', 8, 2)->default(0.50)->after('safety_stock'); // Monthly holding overhead
            $table->boolean('is_perishable')->default(false)->after('storage_cost_rate');
            $table->string('default_location')->nullable()->after('is_perishable'); // e.g. Aisle 1, Rack B, Bin 3
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['secondary_unit_id']);
            $table->dropColumn([
                'category_id',
                'unit_id',
                'secondary_unit_id',
                'sku',
                'purchase_price',
                'selling_price',
                'valuation_method',
                'reorder_point',
                'safety_stock',
                'storage_cost_rate',
                'is_perishable',
                'default_location',
            ]);
        });
    }
};
