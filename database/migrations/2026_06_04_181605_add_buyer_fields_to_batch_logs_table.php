<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('batch_logs', function (Blueprint $table) {
            $table->string('batch_listing_id')->nullable();
            $table->string('batch_code')->nullable();
            $table->string('sensor_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_logs', function (Blueprint $table) {
            $table->dropColumn(['batch_listing_id', 'batch_code', 'sensor_id']);
        });
    }
};
