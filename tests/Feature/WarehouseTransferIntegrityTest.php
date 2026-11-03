<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('transfers.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_create_transfer(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('transfers.create'));
        $response->assertStatus(403);
    }

    public function test_transfer_requires_different_source_and_destination(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $wh = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('transfers.store'), [
            'from_warehouse_id' => $wh->id,
            'to_warehouse_id' => $wh->id,
            'items' => [
                ['item_id' => 1, 'quantity' => 5],
            ],
        ]);

        $response->assertSessionHasErrors('to_warehouse_id');
    }

    public function test_user_cannot_transfer_from_unassigned_warehouse(): void
    {
        $manager = User::factory()->create([
            'role' => 'warehouse_manager',
            'is_active' => true,
        ]);

        $wh1 = Warehouse::create(['name' => 'WH 1', 'code' => 'WH-01', 'is_active' => true]);
        $wh2 = Warehouse::create(['name' => 'WH 2', 'code' => 'WH-02', 'is_active' => true]);

        // Manager is only assigned to WH 2
        $manager->warehouses()->attach($wh2->id);

        $response = $this->actingAs($manager)->post(route('transfers.store'), [
            'from_warehouse_id' => $wh1->id,
            'to_warehouse_id' => $wh2->id,
            'items' => [
                ['item_id' => 1, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(403);
    }
}
