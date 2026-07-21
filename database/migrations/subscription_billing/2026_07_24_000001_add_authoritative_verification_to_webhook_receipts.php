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
        DB::statement('ALTER TABLE payment_provider_webhook_receipts DROP CONSTRAINT payment_provider_webhook_receipts_status_check');
        Schema::table('payment_provider_webhook_receipts', function (Blueprint $table): void {
            $table->uuid('processing_claim_token')->nullable();
            $table->timestampTz('processing_lease_expires_at', 6)->nullable();
            $table->unsignedInteger('verification_attempt_count')->default(0);
            $table->timestampTz('last_verification_attempt_at', 6)->nullable();
            $table->timestampTz('next_verification_attempt_at', 6)->nullable();
            $table->string('safe_failure_label', 120)->nullable();
            $table->uuid('resolved_payment_id')->nullable();
            $table->string('resolved_payment_attempt_reference', 120)->nullable();
            $table->string('resolved_attempt_relation', 16)->nullable();
            $table->string('verification_outcome', 32)->nullable();
            $table->bigInteger('verified_amount_minor')->nullable();
            $table->string('verified_currency', 3)->nullable();
            $table->boolean('provider_object_correlation_passed')->nullable();
            $table->boolean('environment_correlation_supported')->nullable();
            $table->boolean('environment_correlation_passed')->nullable();
            $table->timestampTz('authoritative_verified_at', 6)->nullable();
            $table->index(['status', 'next_verification_attempt_at']);
            $table->foreign('resolved_payment_id')->references('id')->on('payments')->nullOnDelete();
        });
        DB::statement("ALTER TABLE payment_provider_webhook_receipts ADD CONSTRAINT payment_provider_webhook_receipts_status_check CHECK (status IN ('received','processing','retry_pending','processed','ignored','quarantined','exhausted','failed'))");
        DB::statement("ALTER TABLE payment_provider_webhook_receipts ADD CONSTRAINT payment_provider_webhook_receipts_relation_check CHECK (resolved_attempt_relation IS NULL OR resolved_attempt_relation IN ('current','historical'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_provider_webhook_receipts DROP CONSTRAINT IF EXISTS payment_provider_webhook_receipts_relation_check');
        DB::statement('ALTER TABLE payment_provider_webhook_receipts DROP CONSTRAINT payment_provider_webhook_receipts_status_check');
        DB::table('payment_provider_webhook_receipts')
            ->whereIn('status', ['retry_pending', 'quarantined', 'exhausted'])
            ->update(['status' => 'failed']);
        Schema::table('payment_provider_webhook_receipts', function (Blueprint $table): void {
            $table->dropForeign(['resolved_payment_id']);
            $table->dropIndex(['status', 'next_verification_attempt_at']);
            $table->dropColumn(['processing_claim_token', 'processing_lease_expires_at', 'verification_attempt_count', 'last_verification_attempt_at', 'next_verification_attempt_at', 'safe_failure_label', 'resolved_payment_id', 'resolved_payment_attempt_reference', 'resolved_attempt_relation', 'verification_outcome', 'verified_amount_minor', 'verified_currency', 'provider_object_correlation_passed', 'environment_correlation_supported', 'environment_correlation_passed', 'authoritative_verified_at']);
        });
        DB::statement("ALTER TABLE payment_provider_webhook_receipts ADD CONSTRAINT payment_provider_webhook_receipts_status_check CHECK (status IN ('received','processing','processed','ignored','failed'))");
    }
};
