<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location_address',
        'contact_person',
        'phone',
        'email',
        'capacity_sqm',
        'holding_cost_rate_per_day',
        'department_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity_sqm' => 'decimal:2',
            'holding_cost_rate_per_day' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(InventoryAudit::class);
    }

    /**
     * Compute total inventory valuation in this warehouse
     */
    public function getTotalValuationAttribute(): float
    {
        return (float) $this->batches()
            ->where('status', 'active')
            ->selectRaw('SUM(quantity_remaining * purchase_cost) as total_val')
            ->value('total_val') ?: 0.0;
    }

    /**
     * Compute total active stock quantity
     */
    public function getTotalStockQuantityAttribute(): float
    {
        return (float) $this->batches()
            ->where('status', 'active')
            ->sum('quantity_remaining') ?: 0.0;
    }
}
