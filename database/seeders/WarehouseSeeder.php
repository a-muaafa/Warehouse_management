<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['code' => 'WH-MAIN-01'], [
            'name' => 'مركز اللوجستيات الرئيسي',
            'location_address' => 'المنطقة الصناعية 4، الرياض',
            'contact_person' => 'عمر الغامدي',
            'phone' => '+966 50 123 4567',
            'email' => 'wh-central@company.com',
            'capacity_sqm' => 2500.00,
            'holding_cost_rate_per_day' => 45.00,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate(['code' => 'WH-WEST-02'], [
            'name' => 'مركز التوزيع الغربي',
            'location_address' => 'منطقة ميناء الخُمرة، جدة',
            'contact_person' => 'فهد الحربي',
            'phone' => '+966 55 987 6543',
            'email' => 'wh-west@company.com',
            'capacity_sqm' => 1200.00,
            'holding_cost_rate_per_day' => 25.00,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate(['code' => 'WH-EAST-03'], [
            'name' => 'منشأة الترانزيت الشرقية',
            'location_address' => 'المدينة الصناعية الثانية، الدمام',
            'contact_person' => 'طارق الدوسري',
            'phone' => '+966 54 333 2211',
            'email' => 'wh-east@company.com',
            'capacity_sqm' => 800.00,
            'holding_cost_rate_per_day' => 18.00,
            'is_active' => true,
        ]);
    }
}
