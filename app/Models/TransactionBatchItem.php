<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'item_batch_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }
}
