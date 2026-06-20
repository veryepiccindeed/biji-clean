<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_listings', function (Blueprint $table) {
            $table->string('id')->primary(); // String primary key, e.g. listing-001
            $table->foreignId('exporter_id')->constrained('users')->onDelete('cascade');
            $table->string('batch_code');
            $table->string('name');
            $table->string('variety');
            $table->string('origin');
            $table->string('image_url')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('elevation');
            $table->string('harvest_date');
            $table->string('process');
            $table->string('category');
            $table->integer('price_per_kg');
            $table->integer('stock_kg');
            $table->string('status')->default('draft'); // draft, listed, archived
            $table->timestamp('listed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_listings');
    }
};
