<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_id',
        'exporter_id',
        'batch_id',
        'batch_code',
        'operation',
        'status',
        'tx_hash',
        'error_message',
        'error_type',
        'retryable',
        'label',
        'retry_attempt',
        'retry_count',
        'retry_scheduled_at',
        'blockchain_job_id',
    ];

    protected $casts = [
        'retryable' => 'boolean',
        'retry_scheduled_at' => 'datetime',
    ];

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exporter_id');
    }
}