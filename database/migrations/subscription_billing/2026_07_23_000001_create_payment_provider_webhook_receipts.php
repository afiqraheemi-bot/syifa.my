<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_webhook_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_key', 80);
            $table->string('provider_event_id', 160);
            $table->string('event_type', 120);
            $table->string('status', 32)->default('received');
            $table->string('provider_payment_reference', 160)->nullable();
            $table->string('payment_attempt_reference', 120)->nullable();
            $table->uuid('payment_id')->nullable();
            $table->boolean('signature_verified')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestampTz('received_at', 6);
            $table->timestampTz('processing_started_at', 6)->nullable();
            $table->timestampTz('processed_at', 6)->nullable();
            $table->string('failure_label', 120)->nullable();
            $table->timestampsTz(6);

            $table->unique(['provider_key', 'provider_event_id']);
            $table->index('payment_id');
            $table->index('status');
            $table->index(['provider_key', 'provider_payment_reference']);

            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payment_provider_webhook_receipts
            ADD CONSTRAINT payment_provider_webhook_receipts_status_check
            CHECK (status IN ('received', 'processing', 'processed', 'ignored', 'failed'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_webhook_receipts');
    }
};
