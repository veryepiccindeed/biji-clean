<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->string('type');
            $table->string('type_label');
            $table->string('filename');
            $table->string('url')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};
