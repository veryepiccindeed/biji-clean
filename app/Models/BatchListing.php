<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchListing extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'exporter_id',
        'batch_code',
        'name',
        'variety',
        'origin',
        'image_url',
        'image_alt',
        'elevation',
        'harvest_date',
        'process',
        'category',
        'price_per_kg',
        'stock_kg',
        'status',
        'listed_at',
    ];

    protected $casts = [
        'price_per_kg' => 'integer',
        'stock_kg' => 'integer',
        'listed_at' => 'datetime',
    ];

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exporter_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BatchLog::class, 'batch_listing_id', 'id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BatchSnapshot::class, 'batch_listing_id', 'id');
    }
}
