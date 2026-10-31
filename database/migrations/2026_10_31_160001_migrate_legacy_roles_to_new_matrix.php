<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // Migrate admin -> super_admin
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);

        // Migrate supervisor -> inventory_manager
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'inventory_manager']);

        // Migrate workers based on worker_type
        if (Schema::hasColumn('users', 'worker_type')) {
            DB::table('users')->where('role', 'worker')->where('worker_type', 'intake')->update(['role' => 'storekeeper']);
            DB::table('users')->where('role', 'worker')->where('worker_type', 'auditor')->update(['role' => 'stock_counter']);
            DB::table('users')->where('role', 'worker')->where('worker_type', 'view_only')->update(['role' => 'viewer']);
            // Fallback for any other worker
            DB::table('users')->where('role', 'worker')->update(['role' => 'storekeeper']);
        } else {
            DB::table('users')->where('role', 'worker')->update(['role' => 'storekeeper']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'inventory_manager')->update(['role' => 'supervisor']);
        DB::table('users')->whereIn('role', ['storekeeper', 'stock_counter', 'viewer'])->update(['role' => 'worker']);
    }
};
