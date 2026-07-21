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
        Schema::create('payment_verification_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('provider_webhook_receipt_id')->unique();
            $table->string('status', 32)->default('pending');
            $table->uuid('processing_claim_token')->nullable();
            $table->timestampTz('processing_started_at', 6)->nullable();
            $table->timestampTz('processing_lease_expires_at', 6)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempt_at', 6)->nullable();
            $table->timestampTz('next_attempt_at', 6)->nullable();
            $table->string('result_code', 48)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->foreign('provider_webhook_receipt_id')->references('id')->on('payment_provider_webhook_receipts')->cascadeOnDelete();
            $table->index(['status', 'next_attempt_at']);
        });
        DB::statement("ALTER TABLE payment_verification_applications ADD CONSTRAINT payment_verification_applications_status_check CHECK (status IN ('pending','processing','retry_pending','applied','ignored','reconciliation_required','quarantined','exhausted'))");
        DB::statement("ALTER TABLE payment_verification_applications ADD CONSTRAINT payment_verification_applications_result_check CHECK (result_code IS NULL OR result_code IN ('applied','already_reflected','superseded','regressive','reconciliation_required','invalid_evidence','payment_missing','attempt_mismatch'))");

        Schema::create('payment_reconciliation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('provider_webhook_receipt_id')->unique();
            $table->uuid('payment_id');
            $table->string('payment_attempt_reference', 120);
            $table->string('status', 16)->default('open');
            $table->string('reason_code', 80);
            $table->timestampTz('opened_at', 6);
            $table->timestampsTz(6);
            $table->foreign('provider_webhook_receipt_id')->references('id')->on('payment_provider_webhook_receipts')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payment_reconciliation_cases ADD CONSTRAINT payment_reconciliation_cases_status_check CHECK (status = 'open')");

        Schema::create('payment_integration_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 80);
            $table->uuid('payment_id');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('published_at', 6)->nullable();
            $table->uuid('publish_claim_token')->nullable();
            $table->timestampTz('publish_lease_expires_at', 6)->nullable();
            $table->unsignedInteger('publish_attempt_count')->default(0);
            $table->timestampTz('next_publish_attempt_at', 6)->nullable();
            $table->string('safe_failure_label', 120)->nullable();
            $table->timestampsTz(6);
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->index(['published_at', 'next_publish_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_integration_outbox');
        Schema::dropIfExists('payment_reconciliation_cases');
        Schema::dropIfExists('payment_verification_applications');
    }
};
