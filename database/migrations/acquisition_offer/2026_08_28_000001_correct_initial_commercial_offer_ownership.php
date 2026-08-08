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
            $table->uuid('platform_identity_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX commercial_offers_one_prepared_per_clinic_registration
            ON commercial_offers (clinic_registration_id)
            WHERE status = 'prepared'
              AND owner_kind = 'clinic_registration'
              AND purpose = 'initial_checkout'
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS commercial_offers_one_prepared_per_clinic_registration');

        if (DB::table('commercial_offers')->whereNull('platform_identity_id')->exists()) {
            throw new RuntimeException('Cannot restore mandatory Platform Identity ownership while prospect-owned offers exist.');
        }

        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->uuid('platform_identity_id')->nullable(false)->change();
        });
    }
};
