<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Port extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'full_name',
        'country',
        'city',
        'eta_days',
        'eta_label',
        'shipping_rate_per_kg',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'eta_days' => 'integer',
        'shipping_rate_per_kg' => 'integer',
    ];
}
