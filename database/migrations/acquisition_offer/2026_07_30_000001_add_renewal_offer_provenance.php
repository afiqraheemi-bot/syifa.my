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
        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->string('owner_kind', 32)->default('platform_identity');
            $table->string('purpose', 32)->default('initial_checkout');
            $table->uuid('subscription_id')->nullable();
            $table->string('request_idempotency_key', 160)->nullable();
            $table->unique(['purpose', 'subscription_id', 'request_idempotency_key'], 'commercial_offers_renewal_idempotency_unique');
        });
        DB::statement('DROP INDEX commercial_offers_one_prepared_per_platform_identity');
        DB::statement("CREATE UNIQUE INDEX commercial_offers_one_prepared_per_platform_identity ON commercial_offers (platform_identity_id) WHERE status = 'prepared' AND owner_kind = 'platform_identity'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX commercial_offers_one_prepared_per_platform_identity');
        DB::statement("CREATE UNIQUE INDEX commercial_offers_one_prepared_per_platform_identity ON commercial_offers (platform_identity_id) WHERE status = 'prepared'");
        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->dropUnique('commercial_offers_renewal_idempotency_unique');
            $table->dropColumn(['owner_kind', 'purpose', 'subscription_id', 'request_idempotency_key']);
        });
    }
};
