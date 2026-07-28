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
        Schema::create('provisioning_workflows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_event_id')->unique();
            $table->uuid('subscription_id')->unique();
            $table->uuid('tenant_id')->unique();
            $table->uuid('clinic_registration_id')->unique();
            $table->string('status', 40);
            $table->string('current_step', 80);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('lease_expires_at', 6)->nullable();
            $table->timestampTz('next_attempt_at', 6)->nullable();
            $table->string('safe_failure_label', 120)->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->foreign('source_event_id')
                ->references('id')
                ->on('subscription_integration_outbox')
                ->restrictOnDelete();
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();
            $table->foreign('clinic_registration_id')
                ->references('id')
                ->on('clinic_registrations')
                ->restrictOnDelete();
            $table->index(['status', 'next_attempt_at', 'occurred_at'], 'provisioning_workflows_dispatch_index');
        });

        DB::statement("ALTER TABLE provisioning_workflows ADD CONSTRAINT provisioning_workflows_status_check CHECK (status IN ('pending', 'processing', 'retry_pending', 'completed', 'failed'))");
        DB::statement('ALTER TABLE provisioning_workflows ADD CONSTRAINT provisioning_workflows_attempt_count_check CHECK (attempt_count >= 0)');
        DB::statement('ALTER TABLE provisioning_workflows ADD CONSTRAINT provisioning_workflows_lease_pair_check CHECK ((claim_token IS NULL AND lease_expires_at IS NULL) OR (claim_token IS NOT NULL AND lease_expires_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_workflows');
    }
};
