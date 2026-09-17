<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $items = Item::all();
        $warehouse = Warehouse::first();
        $supplier = Supplier::first();

        foreach ($items as $item) {
            // Batch 1: Primary active batch
            ItemBatch::firstOrCreate([
                'item_id' => $item->id,
                'batch_number' => 'دفعة-'.date('Y').'-001-'.$item->id,
            ], [
                'warehouse_id' => $warehouse?->id,
                'supplier_id' => $supplier?->id,
                'purchase_cost' => $item->purchase_price ?: 15.00,
                'selling_price' => $item->selling_price ?: 25.00,
                'quantity_received' => max(50, $item->quantity),
                'quantity_remaining' => $item->quantity,
                'manufacture_date' => now()->subMonths(3),
                'expiry_date' => now()->addMonths(9),
                'status' => 'active',
                'storage_location' => 'ممر-A-موقع-'.($item->id % 20 + 1),
            ]);

            // For perishable items, create a batch expiring in 15 days
            if ($item->is_perishable || $item->id % 3 === 0) {
                ItemBatch::firstOrCreate([
                    'item_id' => $item->id,
                    'batch_number' => 'دفعة-قريبة-'.date('Y').'-'.$item->id,
                ], [
                    'warehouse_id' => $warehouse?->id,
                    'supplier_id' => $supplier?->id,
                    'purchase_cost' => $item->purchase_price ?: 12.00,
                    'selling_price' => $item->selling_price ?: 20.00,
                    'quantity_received' => 20,
                    'quantity_remaining' => 15,
                    'manufacture_date' => now()->subMonths(5),
                    'expiry_date' => now()->addDays(14), // Expiring in 14 days
                    'status' => 'active',
                    'storage_location' => 'منطقة-تبريد-رف-'.($item->id % 5 + 1),
                ]);
            }
        }
    }
}
