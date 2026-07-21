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
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('commercial_offer_id');
            $table->uuid('clinic_registration_id');
            $table->uuid('platform_identity_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('idempotency_key', 160)->unique();
            $table->string('status', 32);
            $table->string('provider_key', 80)->nullable();
            $table->string('provider_payment_reference', 160)->nullable();
            $table->string('failure_reason_code', 120)->nullable();
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_last_changed_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);

            $table->unique('commercial_offer_id');
            $table->index('clinic_registration_id');
            $table->index('platform_identity_id');
            $table->index(['provider_key', 'provider_payment_reference']);
            $table->index('status');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->string('attempt_reference', 120);
            $table->string('status', 32);
            $table->string('provider_key', 80)->nullable();
            $table->string('provider_payment_reference', 160)->nullable();
            $table->string('failure_reason_code', 120)->nullable();
            $table->timestampTz('started_at', 6);
            $table->timestampTz('last_changed_at', 6);
            $table->unsignedInteger('position');
            $table->timestampsTz(6);

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->unique(['payment_id', 'position']);
            $table->unique(['payment_id', 'attempt_reference']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_currency_check CHECK (currency = 'MYR')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('draft', 'pending', 'action_required', 'succeeded', 'failed', 'cancelled', 'expired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payment_attempts
            ADD CONSTRAINT payment_attempts_status_check
            CHECK (status IN ('draft', 'pending', 'action_required', 'succeeded', 'failed', 'cancelled', 'expired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_amount_check CHECK (amount_minor > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_version_check CHECK (version > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
