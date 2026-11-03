<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Warehouse;
use App\Services\HoldingCostCalculatorService;
use App\Services\InventoryValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationUnitTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    private Warehouse $warehouse;

    private InventoryValuationService $valuationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dept = Department::create(['name' => 'Unit Test Dept']);
        $this->warehouse = Warehouse::create(['name' => 'Main WH', 'code' => 'WH-UT-01', 'holding_cost_rate_per_day' => 10]);
        $this->valuationService = new InventoryValuationService;
    }

    public function test_fifo_orders_by_earliest_received_at_first(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 50,
            'valuation_method' => 'fifo',
        ]);

        $batchNewer = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-NEWER',
            'purchase_cost' => 20.00,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'received_at' => now()->subDays(2),
            'status' => 'active',
        ]);

        $batchOlder = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-OLDER',
            'purchase_cost' => 10.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'received_at' => now()->subDays(10),
            'status' => 'active',
        ]);

        $allocations = $this->valuationService->allocateOutboundStock($item, 25, $this->warehouse->id);

        $this->assertCount(2, $allocations);
        $this->assertEquals('BATCH-OLDER', $allocations[0]['batch_number']);
        $this->assertEquals(20.0, $allocations[0]['quantity']);
        $this->assertEquals('BATCH-NEWER', $allocations[1]['batch_number']);
        $this->assertEquals(5.0, $allocations[1]['quantity']);

        $this->assertEquals(0.0, (float) $batchOlder->fresh()->quantity_remaining);
        $this->assertEquals('depleted', $batchOlder->fresh()->status);
        $this->assertEquals(25.0, (float) $batchNewer->fresh()->quantity_remaining);
    }

    public function test_lifo_orders_by_latest_received_at_first(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 50,
            'valuation_method' => 'lifo',
        ]);

        $batchOlder = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LIFO-OLD',
            'purchase_cost' => 10.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'received_at' => now()->subDays(10),
            'status' => 'active',
        ]);

        $batchNewer = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LIFO-NEW',
            'purchase_cost' => 20.00,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'received_at' => now()->subDays(1),
            'status' => 'active',
        ]);

        $allocations = $this->valuationService->allocateOutboundStock($item, 35, $this->warehouse->id);

        $this->assertCount(2, $allocations);
        $this->assertEquals('LIFO-NEW', $allocations[0]['batch_number']);
        $this->assertEquals(30.0, $allocations[0]['quantity']);
        $this->assertEquals('LIFO-OLD', $allocations[1]['batch_number']);
        $this->assertEquals(5.0, $allocations[1]['quantity']);

        $this->assertEquals(0.0, (float) $batchNewer->fresh()->quantity_remaining);
        $this->assertEquals(15.0, (float) $batchOlder->fresh()->quantity_remaining);
    }

    public function test_fefo_orders_by_nearest_expiry_date_and_skips_expired_batches(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 60,
            'valuation_method' => 'fefo',
            'is_perishable' => true,
        ]);

        // Expired batch — must NOT be selected!
        $expiredBatch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'EXP-PAST',
            'purchase_cost' => 8.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'expiry_date' => now()->subDays(3),
            'status' => 'active',
        ]);

        // Expiring in 60 days
        $laterExpiryBatch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'EXP-60D',
            'purchase_cost' => 12.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addDays(60),
            'status' => 'active',
        ]);

        // Expiring in 10 days (soonest valid expiry)
        $soonestExpiryBatch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'EXP-10D',
            'purchase_cost' => 10.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'expiry_date' => now()->addDays(10),
            'status' => 'active',
        ]);

        $allocations = $this->valuationService->allocateOutboundStock($item, 25, $this->warehouse->id);

        $this->assertCount(2, $allocations);
        $this->assertEquals('EXP-10D', $allocations[0]['batch_number']);
        $this->assertEquals(20.0, $allocations[0]['quantity']);
        $this->assertEquals('EXP-60D', $allocations[1]['batch_number']);
        $this->assertEquals(5.0, $allocations[1]['quantity']);

        // Expired batch untouched
        $this->assertEquals(20.0, (float) $expiredBatch->fresh()->quantity_remaining);
        $this->assertEquals(0.0, (float) $soonestExpiryBatch->fresh()->quantity_remaining);
        $this->assertEquals(15.0, (float) $laterExpiryBatch->fresh()->quantity_remaining);
    }

    public function test_valuation_distinguishes_actual_batch_cost_vs_weighted_average_cost(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 10,
            'valuation_method' => 'lifo',
        ]);

        // Batch 1: Received 10 @ $10, still has 10 remaining
        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'VAL-B1',
            'purchase_cost' => 10.00,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'received_at' => now()->subDays(5),
            'status' => 'active',
        ]);

        // Batch 2: Received 10 @ $20, but depleted (sold under LIFO)
        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'VAL-B2',
            'purchase_cost' => 20.00,
            'quantity_received' => 10,
            'quantity_remaining' => 0,
            'received_at' => now()->subDays(1),
            'status' => 'depleted',
        ]);

        // Actual remaining batch value = 10 * $10 = $100
        // FIFO theoretical ending inventory (latest 10 units received) = 10 * $20 = $200
        // Weighted Average unit cost across all receipts = (10*10 + 10*20) / 20 = $15 -> 10 * $15 = $150
        $valuation = $this->valuationService->calculateItemValuation($item, $this->warehouse->id);

        $this->assertEquals(100.00, $valuation['actual_batch_value']);
        $this->assertEquals(200.00, $valuation['fifo_value']);
        $this->assertEquals(150.00, $valuation['weighted_average_value']);
        $this->assertEquals(15.00, $valuation['weighted_average_unit_cost']);
    }

    public function test_holding_cost_calculator_scopes_to_specific_warehouse(): void
    {
        $wh2 = Warehouse::create(['name' => 'Secondary WH', 'code' => 'WH-UT-02', 'holding_cost_rate_per_day' => 5]);
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 100,
            'purchase_price' => 12.00,
            'storage_cost_rate' => 1.00,
        ]);

        // 30 units in WH 1 @ $12
        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'WH1-B1',
            'purchase_cost' => 12.00,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'status' => 'active',
        ]);

        // 70 units in WH 2 @ $12
        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $wh2->id,
            'batch_number' => 'WH2-B1',
            'purchase_cost' => 12.00,
            'quantity_received' => 70,
            'quantity_remaining' => 70,
            'status' => 'active',
        ]);

        $holdingService = new HoldingCostCalculatorService;
        $wh1Cost = $holdingService->calculateItemHoldingCost($item, 0.12, $this->warehouse->id);
        $wh2Cost = $holdingService->calculateItemHoldingCost($item, 0.12, $wh2->id);

        // WH1: 30 units * $12 = $360 capital. Monthly capital @ 12% = $3.60. Space = 30 * $1 = $30. Total = $33.60
        $this->assertEquals(33.60, $wh1Cost['monthly_cost']);
        // WH2: 70 units * $12 = $840 capital. Monthly capital @ 12% = $8.40. Space = 70 * $1 = $70. Total = $78.40
        $this->assertEquals(78.40, $wh2Cost['monthly_cost']);
    }

    public function test_batch_expiry_check_helpers(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id]);

        $expired = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'B-EXP',
            'purchase_cost' => 5,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiry_date' => now()->subDay(),
            'status' => 'active',
        ]);

        $expiringSoon = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'B-SOON',
            'purchase_cost' => 5,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(15),
            'status' => 'active',
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isExpiringSoon(30));
        $this->assertFalse($expiringSoon->isExpired());
        $this->assertTrue($expiringSoon->isExpiringSoon(30));
    }
}
