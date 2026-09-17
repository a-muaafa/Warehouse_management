<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(['company_name' => 'مؤسسة التوريد الصناعي العالمية'], [
            'contact_person' => 'خالد منصور',
            'email' => 'sales@saudiglobal.com',
            'phone' => '+966 11 445 6789',
            'tax_number' => '300123456700003',
            'address' => 'الرياض، مخرج 17 الصناعي',
            'is_active' => true,
        ]);

        Supplier::firstOrCreate(['company_name' => 'أبكس لتجارة المواد الخام'], [
            'contact_person' => 'ناصر السبيعي',
            'email' => 'orders@apextrading.sa',
            'phone' => '+966 12 667 8901',
            'tax_number' => '300987654300003',
            'address' => 'حي الأعمال، جدة',
            'is_active' => true,
        ]);

        Customer::firstOrCreate(['name' => 'الصناعات الثقيلة الفطيم'], [
            'company_name' => 'شركة الفطيم للمقاولات',
            'email' => 'procurement@alfuttaim-heavy.com',
            'phone' => '+966 13 889 0123',
            'tax_number' => '300456789000003',
            'address' => 'طريق ميناء الخبر التجاري',
            'is_active' => true,
        ]);

        Customer::firstOrCreate(['name' => 'الإنشاءات والتصنيع الحديث'], [
            'company_name' => 'مجموعة البناء الحديث',
            'email' => 'purchasing@modernbuild.sa',
            'phone' => '+966 11 223 3445',
            'tax_number' => '300654321000003',
            'address' => 'طريق الملك فهد، الرياض',
            'is_active' => true,
        ]);
    }
}
