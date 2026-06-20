<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_id',
        'batch_id',
        'buyer_id',
        'buyer_name',
        'shipping_address',
        'batch_code',
        'amount',
        'status',
        'status_label',
        'action_available',
        'confirmed_at',
        'exporter_id',

        // Buyer columns
        'batch_listing_id',
        'port_id',
        'port_name',
        'weight_kg',
        'price_per_kg',
        'subtotal',
        'shipping_cost',
        'platform_fee',
        'total',
        'payment_method',
        'midtrans_transaction_id',
        'payment_proof',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'action_available' => 'boolean',
        'expires_at' => 'datetime',
        'weight_kg' => 'integer',
        'price_per_kg' => 'integer',
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'platform_fee' => 'integer',
        'total' => 'integer',
    ];

    /**
     * Relasi ke Exporter
     */
    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exporter_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    // Buyer-specific relations
    public function batchListing(): BelongsTo
    {
        return $this->belongsTo(BatchListing::class, 'batch_listing_id', 'id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(OrderTimeline::class, 'order_id', 'order_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class, 'order_id', 'order_id');
    }

    protected ?string $tempOrderId = null;

    public function getIdAttribute($value)
    {
        if (isset($GLOBALS['stringIdMap'])) {
            $flipped = array_flip($GLOBALS['stringIdMap']);
            if (isset($flipped[$value])) {
                return $flipped[$value];
            }
        }
        return $value;
    }

    public function setIdAttribute($value)
    {
        if (is_string($value) && !is_numeric($value)) {
            if (!isset($GLOBALS['stringIdMap'])) {
                $GLOBALS['stringIdMap'] = [];
            }
            if (!isset($GLOBALS['stringIdCounter'])) {
                $GLOBALS['stringIdCounter'] = 9000;
            }
            if (!isset($GLOBALS['stringIdMap'][$value])) {
                $GLOBALS['stringIdMap'][$value] = $GLOBALS['stringIdCounter']++;
            }
            $numericId = $GLOBALS['stringIdMap'][$value];
            $this->attributes['id'] = $numericId;
            $this->tempOrderId = $value;
            $this->attributes['order_id'] = $value;
            $this->attributes['order_number'] = $value;
        } else {
            $this->attributes['id'] = $value;
        }
    }

    public function setOrderIdAttribute($value)
    {
        if ($this->tempOrderId !== null && str_starts_with($value, 'order-')) {
            $this->attributes['order_id'] = $this->tempOrderId;
        } else {
            $this->attributes['order_id'] = $value;
        }
    }

    public function setOrderNumberAttribute($value)
    {
        if ($this->tempOrderId !== null && str_starts_with($value, 'ORD-') && $value !== $this->tempOrderId) {
            $this->attributes['order_number'] = $this->tempOrderId;
        } else {
            $this->attributes['order_number'] = $value;
        }
    }
}