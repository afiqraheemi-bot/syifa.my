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
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->uuid('clinic_registration_id')->index();
            $table->uuid('payment_id')->unique();
            $table->uuid('commercial_offer_id');
            $table->string('plan_id', 120);
            $table->string('billing_cycle_id', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32);
            $table->string('entitlement_configuration_version', 120);
            $table->string('entitlement_status', 32);
            $table->jsonb('entitlement_capabilities');
            $table->timestampTz('created_at_domain', 6);
            $table->timestampTz('last_changed_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);

            $table->index(['tenant_id', 'status']);
        });

        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_currency_check CHECK (currency = 'MYR')");
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_period_check CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_version_check CHECK (version > 0)');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('pending','active','payment_action_required','restricted','renewal_due','cancelled','expired','suspended','reactivated'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_entitlement_status_check CHECK (entitlement_status IN ('pending','effective','changed','grace_restricted','suspended','expired','superseded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
