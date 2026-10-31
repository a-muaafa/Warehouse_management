<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouse_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'warehouse_id']);
        });

        // Backfill: Migrate existing single warehouse_id on users to warehouse_user pivot
        if (Schema::hasColumn('users', 'warehouse_id')) {
            $existingUserWarehouses = DB::table('users')
                ->whereNotNull('warehouse_id')
                ->select('id as user_id', 'warehouse_id')
                ->get();

            $now = now();
            $rows = [];
            foreach ($existingUserWarehouses as $record) {
                // Verify warehouse exists before inserting
                $warehouseExists = DB::table('warehouses')->where('id', $record->warehouse_id)->exists();
                if ($warehouseExists) {
                    $rows[] = [
                        'user_id' => $record->user_id,
                        'warehouse_id' => $record->warehouse_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($rows)) {
                DB::table('warehouse_user')->insertOrIgnore($rows);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_user');
    }
};
