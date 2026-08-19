<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_whatsapp_settings', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('recipient_number', 16)->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('booking_whatsapp_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('booking_id');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id', 255)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestampsTz(6);
            $table->unique(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_whatsapp_deliveries');
        Schema::dropIfExists('booking_whatsapp_settings');
    }
};
