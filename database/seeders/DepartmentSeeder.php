<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'name' => 'قسم الأدوية والصيدلة',
                'description' => 'الأدوية، المستحضرات الصيدلانية، المكملات الغذائية، واللقاحات. تتطلب ظروف تخزين خاصة وتاريخ انتهاء صلاحية محدد.',
            ],
            [
                'name' => 'قسم التغذية والمواد الغذائية',
                'description' => 'المنتجات الغذائية، المواد الغذائية الحلال، المكملات الغذائية، والمواد الخام للصناعات الغذائية. تخضع لرقابة الجودة الغذائية.',
            ],
        ];

        Department::truncate();

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
