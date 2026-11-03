<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAuditItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_audit_id',
        'item_id',
        'item_batch_id',
        'book_quantity',
        'counted_quantity',
        'discrepancy_quantity',
        'unit_cost',
        'discrepancy_value',
        'status',
        'discrepancy_reason',
        'reconciliation_notes',
    ];

    protected function casts(): array
    {
        return [
            'book_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'discrepancy_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discrepancy_value' => 'decimal:4',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class, 'inventory_audit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }
}
