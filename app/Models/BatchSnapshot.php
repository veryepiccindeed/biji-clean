<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_listing_id',
        'batch_code',
        'snapshot_date',
        'block_number',
        'transaction_hash',
        'log_count',
        'avg_temperature',
        'avg_humidity',
        'max_temperature',
        'min_temperature',
        'hash',
        'is_verified',
        'verified_at',
        'explorer_url',
    ];

    protected $casts = [
        'block_number' => 'integer',
        'log_count' => 'integer',
        'avg_temperature' => 'float',
        'avg_humidity' => 'float',
        'max_temperature' => 'float',
        'min_temperature' => 'float',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function batchListing(): BelongsTo
    {
        return $this->belongsTo(BatchListing::class, 'batch_listing_id', 'id');
    }
}
