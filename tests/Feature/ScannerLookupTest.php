<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_lookup_returns_correct_item_details(): void
    {
        $dept = Department::create(['name' => 'Logistics']);
        $user = User::factory()->create(['role' => 'worker', 'department_id' => $dept->id]);

        $item = Item::create([
            'name' => 'Optical Sensor Bar',
            'barcode' => 'SCAN-123456',
            'sku' => 'SKU-OPT-99',
            'quantity' => 45,
            'purchase_price' => 22.50,
            'selling_price' => 35.00,
            'department_id' => $dept->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('scanner.lookup'), [
            'code' => 'SCAN-123456',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'item' => [
                    'id' => $item->id,
                    'name' => 'Optical Sensor Bar',
                    'barcode' => 'SCAN-123456',
                    'quantity' => 45,
                ],
            ]);
    }
}
