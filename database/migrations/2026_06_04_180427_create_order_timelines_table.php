<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_timelines', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->string('status');
            $table->boolean('is_current')->default(false);
            $table->timestamp('timestamp');
            $table->string('description')->nullable();
            $table->timestamps();

            // We will link it to orders table (using order_id string as key matching tests)
            // No strict foreign key constraint here to prevent constraints issues since order_id is a string ORD-xxx
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_timelines');
    }
};
