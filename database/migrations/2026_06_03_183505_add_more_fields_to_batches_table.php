<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->decimal('berat_basah', 10, 2)->nullable();
            $table->string('kadar_air_target')->nullable();
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['berat_basah', 'kadar_air_target', 'catatan']);
        });
    }
};
