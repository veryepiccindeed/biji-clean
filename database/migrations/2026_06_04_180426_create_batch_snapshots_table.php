<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('batch_listing_id');
            $table->string('batch_code');
            $table->string('snapshot_date');
            $table->integer('block_number');
            $table->string('transaction_hash');
            $table->integer('log_count');
            $table->float('avg_temperature');
            $table->float('avg_humidity');
            $table->float('max_temperature');
            $table->float('min_temperature');
            $table->string('hash');
            $table->boolean('is_verified')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->string('explorer_url')->nullable();
            $table->timestamps();

            $table->foreign('batch_listing_id')->references('id')->on('batch_listings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_snapshots');
    }
};
