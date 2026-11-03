<?php

namespace Tests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitConversionUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_level_chained_unit_conversion_to_and_from_base(): void
    {
        $piece = Unit::create([
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pc',
            'base_unit_id' => null,
            'conversion_factor' => 1,
        ]);

        $box = Unit::create([
            'name' => 'Box (12 pcs)',
            'code' => 'BOX',
            'symbol' => 'bx',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 12,
        ]);

        $pallet = Unit::create([
            'name' => 'Pallet (10 boxes)',
            'code' => 'PAL',
            'symbol' => 'pl',
            'base_unit_id' => $box->id,
            'conversion_factor' => 10,
        ]);

        // 2 pallets = 2 * 10 boxes * 12 pieces = 240 pieces
        $this->assertEquals(240.0, $pallet->toBase(2));
        $this->assertEquals(2.0, $pallet->fromBase(240));
    }

    public function test_circular_unit_dependency_detection(): void
    {
        $u1 = Unit::create(['name' => 'U1', 'code' => 'U1', 'symbol' => 'u1', 'conversion_factor' => 1]);
        $u2 = Unit::create(['name' => 'U2', 'code' => 'U2', 'symbol' => 'u2', 'base_unit_id' => $u1->id, 'conversion_factor' => 10]);
        $u3 = Unit::create(['name' => 'U3', 'code' => 'U3', 'symbol' => 'u3', 'base_unit_id' => $u2->id, 'conversion_factor' => 5]);

        // Setting u1's base_unit_id to u3 would create cycle u1 -> u3 -> u2 -> u1
        $this->assertTrue($u1->wouldCreateCycle($u3->id));
        $this->assertTrue($u1->wouldCreateCycle($u1->id));
        $this->assertFalse($u3->wouldCreateCycle($u1->id));
    }
}
