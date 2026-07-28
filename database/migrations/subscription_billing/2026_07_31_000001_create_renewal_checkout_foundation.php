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
        Schema::table('subscription_renewals', function (Blueprint $table): void {
            $table->uuid('payment_id')->nullable()->unique();
        });
        Schema::table('subscription_timeline_entries', function (Blueprint $table): void {
            $table->uuid('payment_id')->nullable()->index();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });

        Schema::create('renewal_checkout_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('renewal_id')->unique();
            $table->uuid('payment_id')->unique();
            $table->string('idempotency_key', 160)->unique();
            $table->string('stage', 32);
            $table->timestampTz('commercial_offer_valid_until', 6);
            $table->string('session_id', 160)->nullable()->unique();
            $table->text('redirect_destination')->nullable();
            $table->timestampTz('session_expires_at', 6)->nullable();
            $table->string('expiry_authority', 32)->nullable();
            $table->string('safe_failure_code', 120)->nullable();
            $table->timestampTz('started_at', 6);
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->foreign('renewal_id')->references('id')->on('subscription_renewals')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->index(['stage', 'started_at']);
        });
        DB::statement("ALTER TABLE renewal_checkout_applications ADD CONSTRAINT renewal_checkout_applications_stage_check CHECK (stage IN ('session_pending','session_ready','failed','cancelled'))");
        DB::statement("ALTER TABLE renewal_checkout_applications ADD CONSTRAINT renewal_checkout_applications_expiry_authority_check CHECK (expiry_authority IS NULL OR expiry_authority IN ('provider','commercial_offer'))");
        DB::statement("ALTER TABLE renewal_checkout_applications ADD CONSTRAINT renewal_checkout_applications_session_consistency_check CHECK ((stage = 'session_ready' AND session_id IS NOT NULL AND redirect_destination IS NOT NULL AND session_expires_at IS NOT NULL AND expiry_authority IS NOT NULL) OR (stage <> 'session_ready' AND session_id IS NULL AND redirect_destination IS NULL AND session_expires_at IS NULL AND expiry_authority IS NULL))");

        Schema::create('renewal_outcome_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_event_id')->unique();
            $table->uuid('payment_id');
            $table->uuid('renewal_id');
            $table->string('outcome', 16);
            $table->string('result_code', 48);
            $table->timestampTz('applied_at', 6);
            $table->timestampsTz(6);
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('renewal_id')->references('id')->on('subscription_renewals')->restrictOnDelete();
            $table->index(['payment_id', 'applied_at']);
        });
        DB::statement("ALTER TABLE renewal_outcome_applications ADD CONSTRAINT renewal_outcome_applications_outcome_check CHECK (outcome IN ('succeeded','failed','expired'))");
        DB::statement("ALTER TABLE renewal_outcome_applications ADD CONSTRAINT renewal_outcome_applications_result_check CHECK (result_code IN ('applied','already_reflected','reconciliation_required','payment_mismatch','renewal_missing','invalid_state'))");

        Schema::create('subscription_renewal_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 80);
            $table->unsignedInteger('event_version')->default(1);
            $table->uuid('subscription_id');
            $table->uuid('renewal_id');
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
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->restrictOnDelete();
            $table->foreign('renewal_id')->references('id')->on('subscription_renewals')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->index(['published_at', 'next_publish_attempt_at']);
        });
        DB::statement("ALTER TABLE subscription_renewal_outbox ADD CONSTRAINT subscription_renewal_outbox_type_check CHECK (event_type IN ('RenewalPaymentPending','SubscriptionRenewed','SubscriptionRenewalFailed','SubscriptionRenewalExpired','SubscriptionRenewalReconciliationRequired'))");
        DB::statement('ALTER TABLE subscription_renewal_outbox ADD CONSTRAINT subscription_renewal_outbox_version_check CHECK (event_version = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_outbox');
        Schema::dropIfExists('renewal_outcome_applications');
        Schema::dropIfExists('renewal_checkout_applications');
        Schema::table('subscription_timeline_entries', function (Blueprint $table): void {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });
        Schema::table('subscription_renewals', function (Blueprint $table): void {
            $table->dropUnique(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};
