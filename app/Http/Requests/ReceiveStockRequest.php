<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->hasPermission('stock.receive')) {
            return false;
        }

        if ($this->filled('warehouse_id') && ! $user->canAccessWarehouse((int) $this->input('warehouse_id'))) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'movement_reason' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'manufacture_date' => ['nullable', 'date'],
            'reference_doc' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'unique:transactions,idempotency_key'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
