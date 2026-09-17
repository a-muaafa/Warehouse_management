<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $piece = Unit::firstOrCreate(['code' => 'pcs'], [
            'name' => 'قطعة',
            'symbol' => 'قطعة',
            'base_unit_id' => null,
            'conversion_factor' => 1.0,
            'is_active' => true,
        ]);

        $kg = Unit::firstOrCreate(['code' => 'kg'], [
            'name' => 'كيلوجرام',
            'symbol' => 'كجم',
            'base_unit_id' => null,
            'conversion_factor' => 1.0,
            'is_active' => true,
        ]);

        $meter = Unit::firstOrCreate(['code' => 'm'], [
            'name' => 'متر',
            'symbol' => 'م',
            'base_unit_id' => null,
            'conversion_factor' => 1.0,
            'is_active' => true,
        ]);

        $liter = Unit::firstOrCreate(['code' => 'ltr'], [
            'name' => 'لتر',
            'symbol' => 'لتر',
            'base_unit_id' => null,
            'conversion_factor' => 1.0,
            'is_active' => true,
        ]);

        Unit::firstOrCreate(['code' => 'box'], [
            'name' => 'صندوق (12 قطعة)',
            'symbol' => 'صندوق',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 12.0,
            'is_active' => true,
        ]);

        Unit::firstOrCreate(['code' => 'ctn'], [
            'name' => 'كرتون (24 قطعة)',
            'symbol' => 'كرتون',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 24.0,
            'is_active' => true,
        ]);

        Unit::firstOrCreate(['code' => 'plt'], [
            'name' => 'طبلية / منصة (100 قطعة)',
            'symbol' => 'طبلية',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 100.0,
            'is_active' => true,
        ]);
    }
}
