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
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('platform_identity_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_owner_lineage_check
            CHECK (platform_identity_id IS NOT NULL OR clinic_registration_id IS NOT NULL)
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_owner_lineage_check');

        if (DB::table('payments')->whereNull('platform_identity_id')->exists()) {
            throw new RuntimeException('Cannot restore mandatory Platform Identity ownership while acquisition Payments exist.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('platform_identity_id')->nullable(false)->change();
        });
    }
};
