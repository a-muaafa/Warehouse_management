<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->cascadeOnDelete();
            $table->enum('document_type', [
                'purchase_order',
                'delivery_note',
                'commercial_invoice',
                'inspection_slip',
                'damage_report',
                'return_slip',
                'transfer_manifest',
            ]);
            $table->string('document_number');
            $table->string('reference_party')->nullable(); // Supplier or Customer reference
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_documents');
    }
};
