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
        Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_id')->unique();
        $table->string('order_number')->unique();
        $table->string('buyer_name');
        $table->text('shipping_address')->nullable();
        $table->foreignId('batch_id')->constrained('batches');
        $table->foreignId('buyer_id')->constrained('users');
        $table->string('batch_code');
        $table->decimal('amount', 15, 2); 
        $table->string('status');
        $table->string('status_label');
        $table->timestamp('confirmed_at')->nullable();
        $table->boolean('action_available')->default(false);
        $table->foreignId('exporter_id')->constrained('users')->onDelete('cascade');
        $table->timestamps();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
