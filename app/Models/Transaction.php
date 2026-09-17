<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'user_id',
        'warehouse_id',
        'item_batch_id',
        'supplier_id',
        'customer_id',
        'unit_id',
        'type', // in, out
        'movement_reason', // purchase, sale_delivery, transfer_in, transfer_out, customer_return, supplier_return, damaged, audit_adjustment
        'quantity',
        'unit_price',
        'total_amount',
        'reference_doc',
        'notes',
        'approved_by',
        'status', // completed, pending_approval, cancelled
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MovementDocument::class);
    }

    public function getMovementReasonDisplayNameAttribute(): string
    {
        return match ($this->movement_reason) {
            'purchase' => __('Purchase Receipt'),
            'sale_delivery' => __('Sales Delivery / Order Issue'),
            'customer_return' => __('Customer Return'),
            'supplier_return' => __('Return to Supplier'),
            'damage' => __('Damaged / Expired Goods Write-off'),
            'transfer_in' => __('Transfer In'),
            'transfer_out' => __('Transfer Out'),
            'audit_adjustment' => __('Stock Reconciliation Adjustment'),
            default => $this->movement_reason ? __(ucfirst(str_replace('_', ' ', $this->movement_reason))) : ($this->type === 'in' ? __('Receipt') : __('Issue')),
        };
    }
}
