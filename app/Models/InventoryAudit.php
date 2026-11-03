<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAudit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'audit_number',
        'warehouse_id',
        'department_id',
        'title',
        'type',
        'status',
        'freeze_movements',
        'audited_by',
        'approved_by',
        'started_at',
        'reconciled_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'freeze_movements' => 'boolean',
            'started_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }

    public function getTotalDiscrepancyValueAttribute(): float
    {
        return (float) $this->items()->sum('discrepancy_value');
    }
}
