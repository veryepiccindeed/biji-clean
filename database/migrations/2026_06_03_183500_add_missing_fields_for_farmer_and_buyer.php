<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('phone_verified')->default(false);
            $table->string('coordinates')->nullable();
            $table->integer('profile_completion')->default(0);
            $table->boolean('iot_assigned')->default(false);
            $table->string('iot_sensor_id')->nullable();

            // Buyer fields
            $table->string('company_name')->nullable();
            $table->string('business_id')->nullable();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('farmer_id')->nullable()->constrained('users');
            $table->string('varietas')->nullable();
            $table->string('kebun')->nullable();
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('proses_awal')->nullable();
        });

        Schema::create('batch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id');
            $table->string('log_type');
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->default('iot');
            $table->string('note')->nullable();
            $table->string('note_color')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('batch_id')->on('batches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_logs');

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['farmer_id']);
            $table->dropColumn(['farmer_id', 'varietas', 'kebun', 'desa', 'kecamatan', 'proses_awal']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified', 'coordinates', 'profile_completion', 'iot_assigned', 'iot_sensor_id',
                'company_name', 'business_id',
            ]);
        });
    }
};
