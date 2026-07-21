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
        Schema::create('subscription_activation_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_event_id')->unique();
            $table->uuid('payment_id')->unique();
            $table->uuid('subscription_id')->unique();
            $table->uuid('tenant_id');
            $table->string('status', 32)->default('pending');
            $table->string('result_code', 48)->nullable();
            $table->uuid('processing_claim_token')->nullable();
            $table->timestampTz('processing_started_at', 6)->nullable();
            $table->timestampTz('processing_lease_expires_at', 6)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempt_at', 6)->nullable();
            $table->timestampTz('next_attempt_at', 6)->nullable();
            $table->timestampTz('registered_at', 6);
            $table->timestampTz('completed_at', 6)->nullable();
            $table->string('safe_failure_label', 120)->nullable();
            $table->timestampsTz(6);
            $table->index(['status', 'next_attempt_at']);
        });
        DB::statement("ALTER TABLE subscription_activation_applications ADD CONSTRAINT subscription_activation_applications_status_check CHECK (status IN ('pending','processing','retry_pending','applied','ignored','reconciliation_required','quarantined','exhausted'))");
        DB::statement("ALTER TABLE subscription_activation_applications ADD CONSTRAINT subscription_activation_applications_result_check CHECK (result_code IS NULL OR result_code IN ('applied','already_reflected','superseded','reconciliation_required','invalid_evidence','tenant_mismatch','commercial_offer_mismatch','obligation_mismatch'))");

        Schema::create('subscription_activation_reconciliation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_activation_application_id');
            $table->uuid('payment_id');
            $table->uuid('tenant_id');
            $table->string('reason_code', 80);
            $table->string('status', 16)->default('open');
            $table->timestampTz('opened_at', 6);
            $table->timestampsTz(6);
            $table->unique('subscription_activation_application_id', 'subscription_activation_reconciliation_application_unique');
            $table->foreign('subscription_activation_application_id', 'subscription_activation_reconciliation_application_fk')->references('id')->on('subscription_activation_applications')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE subscription_activation_reconciliation_cases ADD CONSTRAINT subscription_activation_reconciliation_cases_status_check CHECK (status = 'open')");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_activation_reconciliation_cases');
        Schema::dropIfExists('subscription_activation_applications');
    }
};
