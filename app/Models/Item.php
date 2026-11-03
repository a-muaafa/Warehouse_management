<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'barcode',
        'sku',
        'unit',
        'unit_id',
        'secondary_unit_id',
        'quantity',
        'purchase_price',
        'selling_price',
        'valuation_method', // fifo, lifo, fefo, avg
        'min_alert_level',
        'reorder_point',
        'safety_stock',
        'storage_cost_rate',
        'is_perishable',
        'default_location',
        'description',
        'department_id',
        'category_id',
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
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'min_alert_level' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'storage_cost_rate' => 'decimal:2',
            'is_perishable' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function primaryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function secondaryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'secondary_unit_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function auditItems(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_alert_level;
    }

    public function needsReorder(): bool
    {
        return $this->quantity <= ($this->reorder_point ?? $this->min_alert_level);
    }

    /**
     * Total valuation calculation based on active batches or fallback purchase_price
     */
    public function getTotalValuationAttribute(): float
    {
        $batchVal = (float) $this->batches()
            ->where('status', 'active')
            ->selectRaw('SUM(quantity_remaining * purchase_cost) as val')
            ->value('val');

        if ($batchVal > 0) {
            return $batchVal;
        }

        return (float) ($this->quantity * (float) $this->purchase_price);
    }

    /**
     * Estimated Monthly Storage Holding Cost
     */
    public function getEstimatedHoldingCostAttribute(): float
    {
        return (float) ($this->quantity * (float) $this->storage_cost_rate);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_alert_level');
    }

    public function scopeReorderAlert(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'reorder_point');
    }
}
