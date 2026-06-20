<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('batch_photos');
        Schema::create('batch_photos', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id');
            $table->string('photo_path');
            $table->string('photo_url');
            $table->string('filename')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('uploader_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_photos');
    }
};
