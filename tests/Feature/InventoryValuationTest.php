<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifo_deduction_order(): void
    {
        $dept = Department::create(['name' => 'Logistics']);
        $warehouse = Warehouse::create(['name' => 'Main WH', 'code' => 'WH-TEST-01']);

        $item = Item::create([
            'name' => 'Hydraulic Valve',
            'barcode' => 'TEST-VALVE-01',
            'quantity' => 100,
            'purchase_price' => 10.00,
            'valuation_method' => 'fifo',
            'department_id' => $dept->id,
        ]);

        // Batch 1: 30 units at $10.00 each
        $batch1 = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'LOT-001',
            'purchase_cost' => 10.00,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'status' => 'active',
            'created_at' => now()->subDays(5),
        ]);

        // Batch 2: 70 units at $15.00 each
        $batch2 = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'LOT-002',
            'purchase_cost' => 15.00,
            'quantity_received' => 70,
            'quantity_remaining' => 70,
            'status' => 'active',
            'created_at' => now()->subDays(2),
        ]);

        $service = new InventoryValuationService;

        // Request 40 units (should exhaust batch1 30 units + take 10 units from batch2)
        $allocations = $service->allocateOutboundStock($item, 40);

        $this->assertCount(2, $allocations);
        $this->assertEquals('LOT-001', $allocations[0]['batch_number']);
        $this->assertEquals(30, $allocations[0]['quantity']);
        $this->assertEquals(10.00, $allocations[0]['unit_cost']);

        $this->assertEquals('LOT-002', $allocations[1]['batch_number']);
        $this->assertEquals(10, $allocations[1]['quantity']);
        $this->assertEquals(15.00, $allocations[1]['unit_cost']);

        $this->assertEquals(0, $batch1->fresh()->quantity_remaining);
        $this->assertEquals('depleted', $batch1->fresh()->status);
        $this->assertEquals(60, $batch2->fresh()->quantity_remaining);
    }
}
