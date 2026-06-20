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
        Schema::create('blockchain_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_id')->unique(); // API identifier
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->foreignId('exporter_id')->constrained('users')->onDelete('cascade');
            $table->string('batch_code');
            $table->string('operation'); // acquisition, certification, dll
            $table->string('status'); // success, pending, failed
            $table->string('tx_hash')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_type')->nullable(); // GasEstimationFailed, Timeout, etc
            $table->boolean('retryable')->default(false);
            $table->string('label')->nullable();
            $table->integer('retry_attempt')->default(0);
            $table->string('retry_count')->default(0);
            $table->timestamp('retry_scheduled_at')->nullable();
            $table->string('blockchain_job_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blockchain_logs');
    }
};
