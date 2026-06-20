<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotData extends Model
{
    use HasFactory;

    // PENTING: Beritahu Laravel untuk pakai koneksi supabase
    protected $connection = 'supabase'; 
    
    // Sesuaikan dengan nama tabelmu di Supabase
    protected $table = 'prediksi_kopi'; 

    protected $guarded = [];
}