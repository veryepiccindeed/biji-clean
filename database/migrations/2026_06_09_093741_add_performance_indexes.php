<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance indexes identified during backend audit.
     * Each index targets a specific query pattern found in controllers.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->index('status');
            $table->index(['farmer_id', 'status']);
            $table->index(['exporter_id', 'status']);
        });

        Schema::table('batch_logs', function (Blueprint $table) {
            $table->index(['batch_id', 'created_at']);
            $table->index(['batch_id', 'source', 'created_at']);
        });

        Schema::table('batch_snapshots', function (Blueprint $table) {
            $table->index(['batch_listing_id', 'snapshot_date']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['buyer_id', 'status']);
            $table->index(['exporter_id', 'status']);
            $table->index('batch_listing_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('blockchain_logs', function (Blueprint $table) {
            $table->index(['exporter_id', 'status']);
        });

        Schema::table('batch_photos', function (Blueprint $table) {
            $table->index('batch_id');
        });

        Schema::table('order_documents', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('order_timelines', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['farmer_id', 'status']);
            $table->dropIndex(['exporter_id', 'status']);
        });

        Schema::table('batch_logs', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'created_at']);
            $table->dropIndex(['batch_id', 'source', 'created_at']);
        });

        Schema::table('batch_snapshots', function (Blueprint $table) {
            $table->dropIndex(['batch_listing_id', 'snapshot_date']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['buyer_id', 'status']);
            $table->dropIndex(['exporter_id', 'status']);
            $table->dropIndex(['batch_listing_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_read']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('blockchain_logs', function (Blueprint $table) {
            $table->dropIndex(['exporter_id', 'status']);
        });

        Schema::table('batch_photos', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
        });

        Schema::table('order_documents', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('order_timelines', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
