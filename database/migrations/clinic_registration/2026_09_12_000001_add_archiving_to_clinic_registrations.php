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
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->timestampTz('archived_at', 6)->nullable();
            $table->uuid('archived_by_platform_identity_id')->nullable();
            $table->index('archived_at', 'clinic_registrations_archived_at_index');
        });

        DB::statement('DROP INDEX clinic_registrations_one_active_per_platform_identity');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_one_active_per_platform_identity
            ON clinic_registrations (platform_identity_id)
            WHERE archived_at IS NULL
                AND status IN ('draft', 'submitted', 'under_review', 'correction_requested', 'approved')
            SQL);
    }

    public function down(): void
    {
        DB::table('clinic_registrations')
            ->whereNotNull('archived_at')
            ->whereIn('status', ['draft', 'submitted', 'under_review', 'correction_requested', 'approved'])
            ->update(['status' => 'expired']);

        DB::statement('DROP INDEX clinic_registrations_one_active_per_platform_identity');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_one_active_per_platform_identity
            ON clinic_registrations (platform_identity_id)
            WHERE status IN ('draft', 'submitted', 'under_review', 'correction_requested', 'approved')
            SQL);

        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->dropIndex('clinic_registrations_archived_at_index');
            $table->dropColumn(['archived_at', 'archived_by_platform_identity_id']);
        });
    }
};
