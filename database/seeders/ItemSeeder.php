<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $pharmaDept = Department::where('name', 'like', 'ادوية%')->orWhere('name', 'like', '%Pharmaceuticals%')->first();
        $foodDept = Department::where('name', 'like', 'غذائية%')->orWhere('name', 'like', '%Food%')->first();

        $p = $pharmaDept?->id ?? 1;
        $f = $foodDept?->id ?? 2;

        $items = [
            // الأدوية والصيدلة (القسم 1)
            ['name' => 'اموكسيسيلين 500 مجم كبسولات', 'barcode' => 'PHRM-AMOX-500-CAP', 'unit' => 'box', 'quantity' => 320, 'min_alert_level' => 50, 'department_id' => $p],
            ['name' => 'باراسيتامول 500 مجم اقراص', 'barcode' => 'PHRM-PARA-500-TAB', 'unit' => 'box', 'quantity' => 850, 'min_alert_level' => 100, 'department_id' => $p],
            ['name' => 'ازيثروميسين 250 مجم اقراص', 'barcode' => 'PHRM-AZTH-250-TAB', 'unit' => 'box', 'quantity' => 180, 'min_alert_level' => 30, 'department_id' => $p],
            ['name' => 'اوميبرازول 20 مجم كبسولات', 'barcode' => 'PHRM-OMEP-020-CAP', 'unit' => 'box', 'quantity' => 420, 'min_alert_level' => 60, 'department_id' => $p],
            ['name' => 'ميتفورمين 500 مجم اقراص', 'barcode' => 'PHRM-METF-500-TAB', 'unit' => 'box', 'quantity' => 240, 'min_alert_level' => 40, 'department_id' => $p],
            ['name' => 'املوديبين 5 مجم اقراص', 'barcode' => 'PHRM-AMLD-005-TAB', 'unit' => 'box', 'quantity' => 14, 'min_alert_level' => 30, 'department_id' => $p],
            ['name' => 'سيتريزين 10 مجم اقراص', 'barcode' => 'PHRM-CETR-010-TAB', 'unit' => 'box', 'quantity' => 510, 'min_alert_level' => 80, 'department_id' => $p],
            ['name' => 'ايبوبروفين 400 مجم اقراص', 'barcode' => 'PHRM-IBUP-400-TAB', 'unit' => 'box', 'quantity' => 630, 'min_alert_level' => 100, 'department_id' => $p],
            ['name' => 'محلول ملحي 0.9% 500 مل', 'barcode' => 'PHRM-NS09-500ML-IV', 'unit' => 'piece', 'quantity' => 1200, 'min_alert_level' => 200, 'department_id' => $p],
            ['name' => 'محلول ديكستروز 5% 500 مل', 'barcode' => 'PHRM-DEX5-500ML-IV', 'unit' => 'piece', 'quantity' => 8, 'min_alert_level' => 150, 'department_id' => $p],
            ['name' => 'ابر حقن طبية معقمة 3 مل', 'barcode' => 'PHRM-SYR-003ML-ST', 'unit' => 'box', 'quantity' => 95, 'min_alert_level' => 20, 'department_id' => $p],
            ['name' => 'قفازات لاتكس طبية معقمة', 'barcode' => 'PHRM-GLVX-M-STER', 'unit' => 'box', 'quantity' => 220, 'min_alert_level' => 40, 'department_id' => $p],
            ['name' => 'فيتامين C 1000 مجم فوار', 'barcode' => 'PHRM-VITC-1000-EFF', 'unit' => 'tube', 'quantity' => 190, 'min_alert_level' => 35, 'department_id' => $p],
            ['name' => 'فيتامين D3 5000 وحدة كبسول', 'barcode' => 'PHRM-VITD-5000-CAP', 'unit' => 'box', 'quantity' => 7, 'min_alert_level' => 20, 'department_id' => $p],
            ['name' => 'زنك 50 مجم اقراص', 'barcode' => 'PHRM-ZINC-050-TAB', 'unit' => 'box', 'quantity' => 155, 'min_alert_level' => 25, 'department_id' => $p],
            ['name' => 'شريط قياس سكر 50 شريط', 'barcode' => 'PHRM-GLCS-STR-50P', 'unit' => 'box', 'quantity' => 88, 'min_alert_level' => 20, 'department_id' => $p],
            ['name' => 'شاش طبي معقم 10x10 سم', 'barcode' => 'PHRM-GAZ-10X10-ST', 'unit' => 'pack', 'quantity' => 340, 'min_alert_level' => 50, 'department_id' => $p],
            ['name' => 'لاصق طبي هايبافيكس 10 سم', 'barcode' => 'PHRM-HYPX-10CM-RL', 'unit' => 'roll', 'quantity' => 60, 'min_alert_level' => 12, 'department_id' => $p],
            ['name' => 'ميزان ضغط الدم رقمي', 'barcode' => 'PHRM-BPM-DIGI-001', 'unit' => 'piece', 'quantity' => 25, 'min_alert_level' => 5, 'department_id' => $p],
            ['name' => 'مطهر كلوريد بنزالكونيوم 500 مل', 'barcode' => 'PHRM-BENZ-500ML-ANT', 'unit' => 'bottle', 'quantity' => 75, 'min_alert_level' => 15, 'department_id' => $p],

            // التغذية والمواد الغذائية (القسم 2)
            ['name' => 'زيت زيتون بكر ممتاز 1 لتر', 'barcode' => 'FOOD-OLIV-EVOO-1LT', 'unit' => 'bottle', 'quantity' => 540, 'min_alert_level' => 80, 'department_id' => $f],
            ['name' => 'عسل طبيعي نحل سدر 500 جم', 'barcode' => 'FOOD-HNYS-SIDR-500', 'unit' => 'jar', 'quantity' => 210, 'min_alert_level' => 30, 'department_id' => $f],
            ['name' => 'تمر مجدول فاخر 1 كجم', 'barcode' => 'FOOD-DATE-MEDJL-1K', 'unit' => 'box', 'quantity' => 380, 'min_alert_level' => 60, 'department_id' => $f],
            ['name' => 'ارز بسمتي 5 كجم', 'barcode' => 'FOOD-RICE-BSMT-5KG', 'unit' => 'bag', 'quantity' => 620, 'min_alert_level' => 100, 'department_id' => $f],
            ['name' => 'دقيق قمح ابيض 50 كجم', 'barcode' => 'FOOD-FLUR-WHET-50K', 'unit' => 'bag', 'quantity' => 280, 'min_alert_level' => 50, 'department_id' => $f],
            ['name' => 'سكر ابيض مكرر 50 كجم', 'barcode' => 'FOOD-SUGR-REFN-50K', 'unit' => 'bag', 'quantity' => 9, 'min_alert_level' => 40, 'department_id' => $f],
            ['name' => 'حليب مجفف كامل الدسم 25 كجم', 'barcode' => 'FOOD-MILK-PWDR-25K', 'unit' => 'bag', 'quantity' => 145, 'min_alert_level' => 25, 'department_id' => $f],
            ['name' => 'بروتين مصل حليب 1 كجم', 'barcode' => 'FOOD-WHEY-ISO-1KGR', 'unit' => 'bag', 'quantity' => 95, 'min_alert_level' => 20, 'department_id' => $f],
            ['name' => 'كريتين مونوهيدرات 500 جم', 'barcode' => 'FOOD-CRTN-MONO-500', 'unit' => 'bag', 'quantity' => 65, 'min_alert_level' => 15, 'department_id' => $f],
            ['name' => 'اوميجا 3 سمك 1000 مجم', 'barcode' => 'FOOD-OMG3-1000-GEL', 'unit' => 'box', 'quantity' => 175, 'min_alert_level' => 30, 'department_id' => $f],
            ['name' => 'شاي اخضر ياباني ماتشا 100 جم', 'barcode' => 'FOOD-TEA-MATC-100G', 'unit' => 'bag', 'quantity' => 4, 'min_alert_level' => 20, 'department_id' => $f],
            ['name' => 'قهوة عربية بالهيل 500 جم', 'barcode' => 'FOOD-CAFE-ARBC-500', 'unit' => 'bag', 'quantity' => 290, 'min_alert_level' => 50, 'department_id' => $f],
            ['name' => 'خل التفاح العضوي 500 مل', 'barcode' => 'FOOD-VINEGR-ACV-500', 'unit' => 'bottle', 'quantity' => 120, 'min_alert_level' => 20, 'department_id' => $f],
            ['name' => 'بذور الشيا العضوية 500 جم', 'barcode' => 'FOOD-SEED-CHIA-500', 'unit' => 'bag', 'quantity' => 88, 'min_alert_level' => 15, 'department_id' => $f],
            ['name' => 'لوز نيء غير محمص 1 كجم', 'barcode' => 'FOOD-NUTS-ALMD-1KG', 'unit' => 'bag', 'quantity' => 160, 'min_alert_level' => 25, 'department_id' => $f],
            ['name' => 'كينوا بيضاء عضوية 500 جم', 'barcode' => 'FOOD-GRNS-QNOA-500', 'unit' => 'bag', 'quantity' => 6, 'min_alert_level' => 18, 'department_id' => $f],
            ['name' => 'ملح هيمالايا وردي 1 كجم', 'barcode' => 'FOOD-SALT-HIM-1KGR', 'unit' => 'bag', 'quantity' => 310, 'min_alert_level' => 40, 'department_id' => $f],
            ['name' => 'كولاجين مائي سائل 500 مل', 'barcode' => 'FOOD-COLG-LQD-500M', 'unit' => 'bottle', 'quantity' => 45, 'min_alert_level' => 10, 'department_id' => $f],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(['barcode' => $item['barcode']], $item);
        }
    }
}
