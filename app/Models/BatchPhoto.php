<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'photo_path',
        'photo_url',
        'filename',
        'uploader_id',
        'note',
    ];
}
