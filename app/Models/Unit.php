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
     * Convert given quantity from this unit to the root base unit (supports multi-level chains).
     */
    public function toBase(float $quantity): float
    {
        $result = $quantity * (float) $this->conversion_factor;
        $visited = [$this->id];
        $parent = $this->base_unit_id ? $this->baseUnit : null;

        while ($parent && ! in_array($parent->id, $visited, true)) {
            $visited[] = $parent->id;
            $result *= (float) $parent->conversion_factor;
            $parent = $parent->base_unit_id ? $parent->baseUnit : null;
        }

        return round($result, 4);
    }

    /**
     * Convert given quantity in root base unit into this unit (supports multi-level chains).
     */
    public function fromBase(float $baseQuantity): float
    {
        $factor = $this->toBase(1.0);
        if ($factor === 0.0) {
            return $baseQuantity;
        }

        return round($baseQuantity / $factor, 4);
    }

    /**
     * Check if setting $proposedBaseUnitId as base_unit_id would create a circular reference.
     */
    public function wouldCreateCycle(?int $proposedBaseUnitId): bool
    {
        if (! $proposedBaseUnitId) {
            return false;
        }

        if ($this->id && $proposedBaseUnitId === $this->id) {
            return true;
        }

        $visited = $this->id ? [$this->id] : [];
        $currentId = $proposedBaseUnitId;

        while ($currentId) {
            if (in_array($currentId, $visited, true)) {
                return true;
            }
            $visited[] = $currentId;
            $currentId = self::where('id', $currentId)->value('base_unit_id');
        }

        return false;
    }
}
