<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transaction Integrity Tests
 *
 * Verifies that:
 * - Quantities stay float (no silent int truncation)
 * - Outbound refuses when batches are insufficient
 * - DB transaction atomicity: partial state is never committed
 * - Inbound increments by exact float amount
 * - Outbound FIFO deducts correct batches
 */
class TransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    private Warehouse $warehouse;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dept = Department::create(['name' => 'Test Dept']);
        $this->warehouse = Warehouse::create(['name' => 'Main WH', 'code' => 'WH-001']);
        $this->admin = User::factory()->create(['role' => 'admin', 'department_id' => $this->dept->id]);
    }

    // -----------------------------------------------------------------------
    // Fractional quantities
    // -----------------------------------------------------------------------

    public function test_inbound_accepts_fractional_quantity(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 0]);

        $response = $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 2.5,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(2.5, (float) $item->fresh()->quantity, 0.001);
    }

    public function test_outbound_accepts_fractional_quantity(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 10]);

        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-FRAC',
            'purchase_cost' => 5.00,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'out',
            'quantity' => 3.75,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(6.25, (float) $item->fresh()->quantity, 0.001);
    }

    // -----------------------------------------------------------------------
    // Insufficient stock
    // -----------------------------------------------------------------------

    public function test_outbound_refused_when_batch_stock_insufficient(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 100]);

        // Item says 100 globally but only 5 in batches — allocation should fail
        ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-SMALL',
            'purchase_cost' => 10.00,
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'out',
            'quantity' => 20, // More than batch stock
        ]);

        $response->assertSessionHasErrors('quantity');
        // Quantity must be unchanged
        $this->assertEquals(100, (float) $item->fresh()->quantity);
        $this->assertDatabaseMissing('transactions', ['item_id' => $item->id, 'type' => 'out']);
    }

    public function test_outbound_refused_when_global_quantity_insufficient(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 3]);

        $response = $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'type' => 'out',
            'quantity' => 50,
        ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(3, (float) $item->fresh()->quantity);
    }

    // -----------------------------------------------------------------------
    // Atomicity: no partial state committed
    // -----------------------------------------------------------------------

    public function test_batch_not_modified_when_transaction_would_fail(): void
    {
        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 5]);

        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-ATOMIC',
            'purchase_cost' => 10.00,
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'status' => 'active',
        ]);

        // Try to take 10 from a batch that only has 5
        $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'out',
            'quantity' => 10,
        ]);

        // Batch must still have 5 — no partial deduction
        $this->assertEquals(5, (float) $batch->fresh()->quantity_remaining);
        $this->assertEquals('active', $batch->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // FIFO allocation correctness
    // -----------------------------------------------------------------------

    public function test_fifo_deducts_oldest_batch_first(): void
    {
        $item = Item::factory()->create([
            'department_id' => $this->dept->id,
            'quantity' => 50,
            'valuation_method' => 'fifo',
        ]);

        $oldBatch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'OLD-LOT',
            'purchase_cost' => 10.00,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'status' => 'active',
            'created_at' => now()->subDays(10),
        ]);

        $newBatch = ItemBatch::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'NEW-LOT',
            'purchase_cost' => 15.00,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'status' => 'active',
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)->post('/transactions', [
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'out',
            'quantity' => 25,
        ]);

        // Old batch (20) should be fully depleted, new batch takes 5
        $this->assertEquals(0, (float) $oldBatch->fresh()->quantity_remaining);
        $this->assertEquals('depleted', $oldBatch->fresh()->status);
        $this->assertEquals(25, (float) $newBatch->fresh()->quantity_remaining);
    }

    // -----------------------------------------------------------------------
    // Valuation service — pre-flight check
    // -----------------------------------------------------------------------

    public function test_valuation_service_throws_when_insufficient_batches(): void
    {
        $dept = Department::create(['name' => 'Service Test Dept']);
        $item = Item::factory()->create(['department_id' => $dept->id, 'quantity' => 100]);

        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-SVC',
            'purchase_cost' => 5.00,
            'quantity_received' => 3,
            'quantity_remaining' => 3,
            'status' => 'active',
        ]);

        $service = new InventoryValuationService;

        $this->expectException(\DomainException::class);
        $service->allocateOutboundStock($item, 10);
    }

    public function test_valuation_service_does_not_partially_commit_on_failure(): void
    {
        $dept = Department::create(['name' => 'Partial Test Dept']);
        $item = Item::factory()->create(['department_id' => $dept->id, 'quantity' => 5]);

        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'LOT-PARTIAL',
            'purchase_cost' => 5.00,
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'status' => 'active',
        ]);

        $service = new InventoryValuationService;

        try {
            $service->allocateOutboundStock($item, 10); // Impossible quantity
        } catch (\DomainException) {
            // Expected — verify batch was NOT touched
        }

        // The batch quantity must be unchanged because no partial commit should occur
        $this->assertEquals(5, (float) $batch->fresh()->quantity_remaining);
        $this->assertEquals('active', $batch->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // Permission boundary
    // -----------------------------------------------------------------------

    public function test_view_only_worker_cannot_create_outbound_transaction(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'worker_type' => 'view_only',
            'department_id' => $this->dept->id,
        ]);

        $item = Item::factory()->create(['department_id' => $this->dept->id, 'quantity' => 50]);

        $response = $this->actingAs($worker)->post('/transactions', [
            'item_id' => $item->id,
            'type' => 'out',
            'quantity' => 10,
        ]);

        $response->assertStatus(403);
        $this->assertEquals(50, (float) $item->fresh()->quantity);
    }

    public function test_disabled_user_is_refused_by_role_middleware(): void
    {
        $disabledAdmin = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->actingAs($disabledAdmin)
            ->get('/admin/logs');

        // Should be redirected to login, not given access
        $response->assertRedirect(route('login'));
    }
}
