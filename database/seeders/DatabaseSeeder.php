<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            WarehouseSeeder::class,
            PartnerSeeder::class,
            UserSeeder::class,
            ItemSeeder::class,
            BatchSeeder::class,
            TransactionSeeder::class,
        ]);
    }
}
