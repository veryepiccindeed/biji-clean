<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable();
            $table->string('business_id_type')->nullable();
            $table->string('currency')->default('IDR');
            $table->boolean('notification_order_status')->default(true);
            $table->boolean('notification_payment')->default(true);
            $table->boolean('notification_shipment')->default(true);
            $table->boolean('notification_catalog_update')->default(false);
            $table->boolean('email_reminder')->default(true);
            $table->integer('email_reminder_hours')->default(2);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_url',
                'business_id_type',
                'currency',
                'notification_order_status',
                'notification_payment',
                'notification_shipment',
                'notification_catalog_update',
                'email_reminder',
                'email_reminder_hours',
            ]);
        });
    }
};
