<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'exporter_id',
        'farmer_id',
        'status',
        'batch_code',
        'certificate_pdf_path',
        'blockchain_status',
        'elevation_mdpl',
        'health_status',
        'price',
        'description',
        'varietas',
        'tanggal_panen',
        'metode_panen',
        'jumlah_karung',
        'berat_basah',
        'kebun',
        'desa',
        'kecamatan',
        'proses_awal',
        'kadar_air_target',
        'status_jemur',
        'catatan',
    ];

    protected $casts = [
        'tanggal_panen' => 'date',
    ];

    public function getNameAttribute(): string
    {
        return 'Batch ' . $this->batch_code;
    }

    public function getVarietyAttribute(): ?string
    {
        return $this->varietas;
    }

    public function getQuantityAttribute(): ?int
    {
        return $this->jumlah_karung;
    }

    public function getFarmerNameAttribute(): ?string
    {
        return $this->farmer ? $this->farmer->name : null;
    }

    public function getAcquiredByAttribute(): ?int
    {
        return $this->exporter_id;
    }

    protected static function booted()
    {
        static::updated(function ($batch) {
            if ($batch->wasChanged('status') && $batch->status === 'iot_installed') {
                if ($batch->farmer_id) {
                    $farmer = User::find($batch->farmer_id);
                    if ($farmer) {
                        $farmer->update([
                            'iot_assigned' => true,
                            'iot_sensor_id' => $farmer->iot_sensor_id ?? 'IOT-TOR-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Set attribute untuk sinkronisasi nama/variety otomatis ke field database agar kompatibel mundur
     * jika ada logic yang masih mencoba ngeset ->variety atau ->name
     */
    public function setVarietyAttribute($value)
    {
        $this->attributes['varietas'] = $value;
    }

    public function setQuantityAttribute($value)
    {
        $this->attributes['jumlah_karung'] = $value;
    }

    public function setAcquiredByAttribute($value)
    {
        $this->attributes['exporter_id'] = $value;
    }

    // RELATIONS
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exporter_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BatchLog::class, 'batch_id', 'batch_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BatchPhoto::class, 'batch_id', 'batch_id');
    }
}
