<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pharmaDept = Department::where('name', 'like', '%الأدوية%')->first();
        $foodDept = Department::where('name', 'like', '%التغذية%')->first() ?? Department::where('name', 'like', '%أغذية%')->first();

        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@wms.com',
                'name' => 'المدير العام (أدمن النظام)',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'worker_type' => null,
                'department_id' => null,
                'is_active' => true,
            ],
            [
                'username' => 'sup_food',
                'email' => 'sup.food@wms.com',
                'name' => 'أحمد علي (رئيس قسم الأغذية)',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'worker_type' => null,
                'department_id' => $foodDept?->id,
                'is_active' => true,
            ],
            [
                'username' => 'sup_pharma',
                'email' => 'sup.pharma@wms.com',
                'name' => 'د. خالد العمري (رئيس قسم الأدوية)',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'worker_type' => null,
                'department_id' => $pharmaDept?->id,
                'is_active' => true,
            ],
            [
                'username' => 'worker_entry',
                'email' => 'worker.entry@wms.com',
                'name' => 'محمود سالم (عامل إدخال وتوثيق - أغذية)',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'worker_type' => 'intake',
                'department_id' => $foodDept?->id,
                'is_active' => true,
            ],
            [
                'username' => 'worker_audit',
                'email' => 'worker.audit@wms.com',
                'name' => 'سارة عبد الله (عاملة جرد وفرز - أدوية)',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'worker_type' => 'auditor',
                'department_id' => $pharmaDept?->id,
                'is_active' => true,
            ],
            [
                'username' => 'worker_view',
                'email' => 'worker.view@wms.com',
                'name' => 'يوسف الفهد (عامل مشاهد ومتابع طلبات - أغذية)',
                'password' => Hash::make('password'),
                'role' => 'worker',
                'worker_type' => 'view_only',
                'department_id' => $foodDept?->id,
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['username' => $userData['username']],
                $userData
            );
        }
    }
}
