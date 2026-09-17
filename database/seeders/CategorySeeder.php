<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $electronics = ItemCategory::firstOrCreate(['code' => 'CAT-ELEC'], [
            'name' => 'الإلكترونيات والحساسات',
            'description' => 'مكونات إلكترونية، أسلاك، ومؤشرات رقمية',
        ]);

        ItemCategory::firstOrCreate(['code' => 'CAT-RAW'], [
            'name' => 'المواد الخام',
            'description' => 'معدات، بوليمرات، ومواد خام بالجملة',
        ]);

        ItemCategory::firstOrCreate(['code' => 'CAT-PACK'], [
            'name' => 'مواد التغليف والتعبئة',
            'description' => 'كرتون، رولات تغليف، وأشرطة لاصقة',
        ]);

        ItemCategory::firstOrCreate(['code' => 'CAT-SPARE'], [
            'name' => 'قطع الغيار الميكانيكية',
            'description' => 'محامل، صمامات، وفلاتر هيدروليكية استبدال',
            'parent_id' => $electronics->id,
        ]);
    }
}
