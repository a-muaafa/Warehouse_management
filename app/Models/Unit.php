<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'base_unit_id',
        'conversion_factor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(self::class, 'base_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Convert given quantity from this unit to the base unit.
     */
    public function toBase(float $quantity): float
    {
        return $quantity * (float) $this->conversion_factor;
    }

    /**
     * Convert given quantity in base unit into this unit.
     */
    public function fromBase(float $baseQuantity): float
    {
        if ((float) $this->conversion_factor === 0.0) {
            return $baseQuantity;
        }

        return $baseQuantity / (float) $this->conversion_factor;
    }
}
