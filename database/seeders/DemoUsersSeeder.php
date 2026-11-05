<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Demo seeder is disabled in production.');

            return;
        }

        $this->call(UserSeeder::class);
    }
}
