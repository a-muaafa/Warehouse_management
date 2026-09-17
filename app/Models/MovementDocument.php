<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'document_type',
        'document_number',
        'reference_party',
        'file_path',
        'notes',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
