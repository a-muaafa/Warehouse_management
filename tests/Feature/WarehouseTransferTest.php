<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_transfer_dispatch_and_receive_flow(): void
    {
        $dept = Department::create(['name' => 'Logistics']);
        $wh1 = Warehouse::create(['name' => 'Source WH', 'code' => 'WH-SRC-01']);
        $wh2 = Warehouse::create(['name' => 'Destination WH', 'code' => 'WH-DST-02']);

        $user = User::factory()->create(['role' => 'admin', 'department_id' => $dept->id]);

        $item = Item::create([
            'name' => 'Copper Cable',
            'barcode' => 'TEST-CABLE-01',
            'quantity' => 100,
            'purchase_price' => 5.00,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($user);

        // 1. Create Transfer Request
        $response = $this->post(route('transfers.store'), [
            'from_warehouse_id' => $wh1->id,
            'to_warehouse_id' => $wh2->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 20,
                ],
            ],
        ]);

        $response->assertRedirect(route('transfers.index'));
        $transfer = WarehouseTransfer::first();
        $this->assertEquals('pending', $transfer->status);

        // 2. Dispatch Transfer
        $dispatchResp = $this->post(route('transfers.dispatch', $transfer));
        $dispatchResp->assertRedirect();
        $this->assertEquals('in_transit', $transfer->fresh()->status);

        // 3. Receive Transfer
        $receiveResp = $this->post(route('transfers.receive', $transfer));
        $receiveResp->assertRedirect();
        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertDatabaseHas('item_batches', [
            'item_id' => $item->id,
            'warehouse_id' => $wh2->id,
            'quantity_remaining' => 20,
        ]);
    }
}
