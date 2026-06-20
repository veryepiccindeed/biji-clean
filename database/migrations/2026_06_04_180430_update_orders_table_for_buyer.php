<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->change();
            $table->string('status_label')->nullable()->change();
            $table->foreignId('exporter_id')->nullable()->change();
            $table->string('buyer_name')->nullable()->change();
            $table->string('batch_code')->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();

            // Buyer columns
            $table->string('batch_listing_id')->nullable();
            $table->integer('port_id')->nullable();
            $table->string('port_name')->nullable();
            $table->integer('weight_kg')->nullable();
            $table->integer('price_per_kg')->nullable();
            $table->integer('subtotal')->nullable();
            $table->integer('shipping_cost')->nullable();
            $table->integer('platform_fee')->nullable();
            $table->integer('total')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert changes if needed (not typically executed in tests)
        });
    }
};
