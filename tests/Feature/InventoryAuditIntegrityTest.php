<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('audits.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_create_audit(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($viewer)->get(route('audits.create'));
        $response->assertStatus(403);
    }

    public function test_user_cannot_create_audit_for_unassigned_warehouse(): void
    {
        $manager = User::factory()->create([
            'role' => 'warehouse_manager',
            'is_active' => true,
        ]);

        $wh1 = Warehouse::create(['name' => 'WH 1', 'code' => 'WH-01', 'is_active' => true]);
        $wh2 = Warehouse::create(['name' => 'WH 2', 'code' => 'WH-02', 'is_active' => true]);

        // Manager is only assigned to WH 2
        $manager->warehouses()->attach($wh2->id);

        $response = $this->actingAs($manager)->post(route('audits.store'), [
            'title' => 'Annual Audit',
            'type' => 'cycle_count',
            'warehouse_id' => $wh1->id,
        ]);

        $response->assertStatus(403);
    }
}
