<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

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
        'idempotency_key',
        'notes',
        'approved_by',
        'status', // completed, pending_approval, cancelled
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (auth()->check() && ! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if ($model->getOriginal('status') === 'completed') {
                // Prevent altering core financial/quantity fields of a completed transaction
                $immutableFields = ['item_id', 'warehouse_id', 'type', 'quantity'];
                foreach ($immutableFields as $field) {
                    if ($model->isDirty($field)) {
                        throw new \DomainException('Completed inventory transactions cannot be directly modified; record a corrective adjustment instead.');
                    }
                }
            }
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if ($model->status === 'completed') {
                throw new \DomainException('Cannot delete a completed transaction that has already affected stock balances.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total_amount' => 'decimal:4',
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

    public function batchItems(): HasMany
    {
        return $this->hasMany(TransactionBatchItem::class);
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(ItemBatch::class, 'transaction_batch_items')
            ->withPivot(['quantity', 'unit_cost', 'total_cost'])
            ->withTimestamps();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_id')
            ->where('source_type', self::class);
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
