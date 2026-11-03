<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Central Immutable Inventory Movement Ledger.
 *
 * Records every stock balance change with before/after quantities,
 * batch reference, source document, and performing user.
 * Direct updates or deletions are blocked at the model level.
 */
class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'movement_type',
        'source_type',
        'source_id',
        'item_id',
        'warehouse_id',
        'item_batch_id',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'performed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:4',
            'quantity_change' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Inventory Movement Ledger records are immutable and cannot be modified.');
        });

        static::deleting(function () {
            throw new \DomainException('Inventory Movement Ledger records are immutable and cannot be deleted.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * Record a new immutable ledger movement entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): self
    {
        $qtyBefore = (float) ($attributes['quantity_before'] ?? 0);
        $qtyChange = (float) ($attributes['quantity_change'] ?? 0);
        $qtyAfter = $attributes['quantity_after'] ?? round($qtyBefore + $qtyChange, 4);
        $unitCost = (float) ($attributes['unit_cost'] ?? 0);

        return self::create([
            'reference_number' => $attributes['reference_number'] ?? ('MOV-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid(), -5))),
            'movement_type' => $attributes['movement_type'],
            'source_type' => $attributes['source_type'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
            'item_id' => $attributes['item_id'],
            'warehouse_id' => $attributes['warehouse_id'] ?? null,
            'item_batch_id' => $attributes['item_batch_id'] ?? null,
            'quantity_before' => $qtyBefore,
            'quantity_change' => $qtyChange,
            'quantity_after' => $qtyAfter,
            'unit_cost' => $unitCost,
            'total_cost' => $attributes['total_cost'] ?? round(abs($qtyChange) * $unitCost, 4),
            'performed_by' => $attributes['performed_by'] ?? auth()->id(),
            'notes' => $attributes['notes'] ?? null,
        ]);
    }
}
