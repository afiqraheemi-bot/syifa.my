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
        Schema::create('commercial_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_identity_id');
            $table->uuid('clinic_registration_id');
            $table->string('status', 32);
            $table->string('plan_offering_id', 120);
            $table->string('plan_id', 120);
            $table->string('billing_cycle_id', 120);
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->string('offering_configuration_version', 64);
            $table->string('capability_configuration_reference', 120);
            $table->unsignedBigInteger('subtotal_amount_minor');
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3);
            $table->timestampTz('expires_at');
            $table->uuid('claimed_payment_id')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->uuid('correlation_id');
            $table->unsignedBigInteger('version');
            $table->timestampsTz();

            $table->index(['platform_identity_id', 'status']);
            $table->index(['clinic_registration_id', 'status']);
            $table->index('expires_at');
        });

        Schema::create('commercial_offer_line_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('commercial_offer_id');
            $table->string('item_type', 64);
            $table->string('item_reference', 120);
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3);
            $table->string('catalogue_snapshot_reference', 120);
            $table->unsignedInteger('position');
            $table->timestampsTz();

            $table->foreign('commercial_offer_id')
                ->references('id')
                ->on('commercial_offers')
                ->cascadeOnDelete();
            $table->unique(['commercial_offer_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_status_check
            CHECK (status IN ('prepared', 'claimed', 'cancelled', 'expired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_claim_consistency_check
            CHECK (
                (status = 'claimed' AND claimed_payment_id IS NOT NULL AND claimed_at IS NOT NULL)
                OR (status <> 'claimed' AND claimed_payment_id IS NULL AND claimed_at IS NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_currency_check CHECK (currency = 'MYR')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_total_check CHECK (total_amount_minor = subtotal_amount_minor)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offers
            ADD CONSTRAINT commercial_offers_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE commercial_offer_line_items
            ADD CONSTRAINT commercial_offer_line_items_currency_check CHECK (currency = 'MYR')
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX commercial_offers_one_prepared_per_platform_identity
            ON commercial_offers (platform_identity_id)
            WHERE status = 'prepared'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_line_items');
        Schema::dropIfExists('commercial_offers');
    }
};
