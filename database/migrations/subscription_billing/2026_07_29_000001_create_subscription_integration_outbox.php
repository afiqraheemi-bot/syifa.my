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
        Schema::create('subscription_integration_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 80);
            $table->unsignedInteger('event_version')->default(1);
            $table->uuid('subscription_id');
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
            $table->index(['published_at', 'next_publish_attempt_at']);
        });
        DB::statement("ALTER TABLE subscription_integration_outbox ADD CONSTRAINT subscription_integration_outbox_type_check CHECK (event_type = 'SubscriptionActivated')");
        DB::statement('ALTER TABLE subscription_integration_outbox ADD CONSTRAINT subscription_integration_outbox_version_check CHECK (event_version = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_integration_outbox');
    }
};
