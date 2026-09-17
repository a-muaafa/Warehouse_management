<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $items = Item::all()->keyBy('id');
        $users = User::all()->keyBy('id');

        if ($items->isEmpty() || $users->isEmpty()) {
            return;
        }

        $admin = User::where('role', 'admin')->first() ?? $users->first();
        $workers = User::where('role', 'worker')->get();
        $supervisors = User::where('role', 'supervisor')->get();

        $sampleLogs = [
            // Today transactions
            ['item_barcode' => 'ELEC-RES-10K', 'type' => 'in', 'quantity' => 500, 'notes' => 'توريد شحنة جديدة من المورد الدولي', 'hours_ago' => 1],
            ['item_barcode' => 'ELEC-DEV-ARDUINO', 'type' => 'out', 'quantity' => 10, 'notes' => 'صرف لصالح قسم الإنتاج والتطوير', 'hours_ago' => 2],
            ['item_barcode' => 'PCK-BOX-403030', 'type' => 'out', 'quantity' => 80, 'notes' => 'تجهيز طلبيات الشحن لمتجر الرياض', 'hours_ago' => 3],
            ['item_barcode' => 'MED-MSK-3PLY-50', 'type' => 'in', 'quantity' => 50, 'notes' => 'استلام مستلزمات السلامة والوقاية الدورية', 'hours_ago' => 4],
            ['item_barcode' => 'SPR-BRG-608ZZ-P', 'type' => 'out', 'quantity' => 20, 'notes' => 'صيانة خط الإنتاج رقم 3', 'hours_ago' => 5],
            ['item_barcode' => 'FNS-PWR-BNK-20K', 'type' => 'in', 'quantity' => 50, 'notes' => 'استلام شحنة مصنعية من خط التجميع', 'hours_ago' => 6],

            // Yesterday
            ['item_barcode' => 'RAW-STL-ROD-12M', 'type' => 'in', 'quantity' => 40, 'notes' => 'توريد مواد خام من المورد المحلي', 'hours_ago' => 26],
            ['item_barcode' => 'RAW-COP-WIRE-15', 'type' => 'out', 'quantity' => 15, 'notes' => 'صرف لورشة التشكيل واللف', 'hours_ago' => 28],
            ['item_barcode' => 'PCK-BBL-WRAP-50', 'type' => 'in', 'quantity' => 20, 'notes' => 'توريد رولات التغليف الواقي', 'hours_ago' => 30],
            ['item_barcode' => 'MED-GLV-NITRIL-L', 'type' => 'out', 'quantity' => 15, 'notes' => 'صرف دوري للفنيين والعمال', 'hours_ago' => 32],

            // 2-5 days ago
            ['item_barcode' => 'ELEC-DEV-RPI4-8G', 'type' => 'out', 'quantity' => 8, 'notes' => 'تجهيز نماذج أولية لمعرض التقنية', 'hours_ago' => 50],
            ['item_barcode' => 'RAW-PLS-PP-25KG', 'type' => 'in', 'quantity' => 30, 'notes' => 'استلام حبيبات البلاستيك للحقن', 'hours_ago' => 70],
            ['item_barcode' => 'SPR-OIL-IND-01L', 'type' => 'out', 'quantity' => 4, 'notes' => 'صيانة وتشحيم المضخات الهيدروليكية', 'hours_ago' => 90],
            ['item_barcode' => 'FNS-SCN-2D-WIRE', 'type' => 'out', 'quantity' => 6, 'notes' => 'تزويد فرع المستودع الجنوبي بأجهزة مسح', 'hours_ago' => 110],
            ['item_barcode' => 'PCK-TPE-CLR-48M', 'type' => 'in', 'quantity' => 100, 'notes' => 'تجديد مخزون أشرطة التعبئة', 'hours_ago' => 120],
        ];

        foreach ($sampleLogs as $log) {
            $item = Item::where('barcode', $log['item_barcode'])->first();
            if (! $item) {
                continue;
            }

            // Pick a worker or supervisor in that department or admin
            $user = User::where('department_id', $item->department_id)->first() ?? $admin;

            Transaction::create([
                'item_id' => $item->id,
                'user_id' => $user->id,
                'type' => $log['type'],
                'quantity' => $log['quantity'],
                'notes' => $log['notes'],
                'created_at' => Carbon::now()->subHours($log['hours_ago']),
                'updated_at' => Carbon::now()->subHours($log['hours_ago']),
            ]);
        }
    }
}
