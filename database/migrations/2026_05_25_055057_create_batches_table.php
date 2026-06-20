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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            // ID unik buat batch, misal: PROD-2025-001
            $table->string('batch_id')->unique(); 
            $table->string('batch_code')->unique();
            $table->string('variety');
            $table->string('farmer_name');
            $table->foreignId('acquired_by')->nullable()->constrained('users');
            $table->string('certificate_pdf_path')->nullable(); //
            $table->string('blockchain_status')->default('none'); //
            $table->integer('elevation_mdpl');
            $table->string('health_status')->default('normal');
            $table->decimal('price', 15, 2)->default(0);
            
            // Relasi ke User (Eksportir)
            $table->foreignId('exporter_id')
                ->nullable() // Nullable karena awalnya mungkin belum ada yang akuisisi
                ->constrained('users')
                ->onDelete('cascade');
                
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(0);
            
            // Status: pending (di petani), acquired (di eksportir), shipped, dll.
            $table->string('status')->default('pending'); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
