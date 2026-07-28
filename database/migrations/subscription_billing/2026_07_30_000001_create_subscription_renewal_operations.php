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
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('auto_renew_status', 32)->default('disabled');
            $table->timestampTz('auto_renew_changed_at', 6)->nullable();
        });
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_auto_renew_status_check CHECK (auto_renew_status IN ('disabled','enabled','cancellation_pending','cancelled','failed'))");

        Schema::create('subscription_renewals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->uuid('commercial_offer_id')->unique();
            $table->string('request_idempotency_key', 160);
            $table->string('mode', 16);
            $table->string('status', 32);
            $table->string('plan_id', 120);
            $table->string('billing_cycle_id', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->uuid('requested_actor_id');
            $table->timestampTz('requested_at', 6);
            $table->timestampTz('last_changed_at', 6);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->restrictOnDelete();
            $table->unique(['subscription_id', 'request_idempotency_key']);
            $table->index(['subscription_id', 'status']);
        });
        DB::statement("ALTER TABLE subscription_renewals ADD CONSTRAINT subscription_renewals_status_check CHECK (status IN ('requested','payment_pending','action_required','succeeded','failed','cancelled','expired','reconciliation_required'))");
        DB::statement("CREATE UNIQUE INDEX subscription_renewals_one_open_per_subscription ON subscription_renewals (subscription_id) WHERE status IN ('requested','payment_pending','action_required')");

        Schema::create('subscription_timeline_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->uuid('renewal_id')->nullable();
            $table->string('event_type', 80);
            $table->uuid('actor_id')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at', 6);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->restrictOnDelete();
            $table->foreign('renewal_id')->references('id')->on('subscription_renewals')->restrictOnDelete();
            $table->index(['subscription_id', 'occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_timeline_entries');
        Schema::dropIfExists('subscription_renewals');
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['auto_renew_status', 'auto_renew_changed_at']);
        });
    }
};
