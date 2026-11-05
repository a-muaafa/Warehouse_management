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
        // Base master data safe for all environments
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Demo accounts and sample inventory are strictly restricted to non-production environments
        if (app()->environment(['local', 'testing'])) {
            $this->call([
                WarehouseSeeder::class,
                PartnerSeeder::class,
                DemoUsersSeeder::class,
                ItemSeeder::class,
                BatchSeeder::class,
                TransactionSeeder::class,
            ]);
        }
    }
}
