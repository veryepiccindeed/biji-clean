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
        // 1. Tabel Batches
        Schema::table('batches', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['acquired_by']);
            
            // Drop redundant columns
            $table->dropColumn([
                'variety',      // Duplicate of varietas
                'name',         // Computed from batch_code
                'quantity',     // Duplicate of jumlah_karung
                'acquired_by',  // Duplicate of exporter_id
                'farmer_name'   // Denormalized, can be fetched via farmer_id
            ]);
        });

        // 2. Tabel Batch Logs
        Schema::table('batch_logs', function (Blueprint $table) {
            // Drop redundant references and notes
            $table->dropColumn([
                'batch_code',       // Denormalized, fetch via batch_id
                'batch_listing_id', // Denormalized, fetch via batch_id -> batchListing
                'notes'             // Duplicate of note
            ]);
        });

        // 3. Tabel Orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'buyer_name',
                'batch_code',
                'amount'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('variety')->nullable();
            $table->string('name')->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedBigInteger('acquired_by')->nullable();
            $table->string('farmer_name')->nullable();
        });

        Schema::table('batch_logs', function (Blueprint $table) {
            $table->string('batch_code')->nullable();
            $table->unsignedBigInteger('batch_listing_id')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->unique()->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('batch_code')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
        });
    }
};
