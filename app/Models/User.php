<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'phone',
        'location',
        'language',
        'timezone',
        'notifications_enabled',
        'email_notifications',
        'temperature_unit',
        'phone_verified',
        'profile_completion',
        'iot_assigned',
        'iot_sensor_id',
        'company_name',
        'business_id',
        'avatar_url',

        // Buyer columns
        'business_id_type',
        'currency',
        'notification_order_status',
        'notification_payment',
        'notification_shipment',
        'notification_catalog_update',
        'email_reminder',
        'email_reminder_hours',
        'force_logout',
    ];

    /**
     * Default attribute values (mirrors DB column defaults for buyer fields).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'language' => 'id',
        'currency' => 'IDR',
        'notification_order_status' => true,
        'notification_payment' => true,
        'notification_shipment' => true,
        'notification_catalog_update' => false,
        'email_reminder' => true,
        'email_reminder_hours' => 2,
        'force_logout' => false,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notifications_enabled' => 'boolean',
            'email_notifications' => 'boolean',
            'phone_verified' => 'boolean',
            'iot_assigned' => 'boolean',

            // Buyer casts
            'notification_order_status' => 'boolean',
            'notification_payment' => 'boolean',
            'notification_shipment' => 'boolean',
            'notification_catalog_update' => 'boolean',
            'email_reminder' => 'boolean',
            'email_reminder_hours' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'exporter_id');
    }

    public function blockchainLogs(): HasMany
    {
        return $this->hasMany(BlockchainLog::class, 'exporter_id');
    }
}
