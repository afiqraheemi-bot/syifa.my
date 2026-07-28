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
        Schema::create('clinic_registration_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clinic_registration_id');
            $table->string('outcome', 32);
            $table->string('reason_category', 100);
            $table->text('correction_instructions')->nullable();
            $table->uuid('decided_by_platform_identity_id');
            $table->timestampTz('decided_at', 6);
            $table->timestampTz('superseded_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->foreign('clinic_registration_id')
                ->references('id')
                ->on('clinic_registrations')
                ->cascadeOnDelete();
            $table->index(
                ['clinic_registration_id', 'decided_at'],
                'clinic_registration_decisions_timeline_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registration_decisions
            ADD CONSTRAINT clinic_registration_decisions_outcome_check
            CHECK (outcome IN ('approved', 'rejected', 'correction_requested'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registration_decisions
            ADD CONSTRAINT clinic_registration_decisions_correction_check
            CHECK (
                (outcome = 'correction_requested' AND correction_instructions IS NOT NULL AND btrim(correction_instructions) <> '')
                OR outcome <> 'correction_requested'
            )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registration_decisions_one_current
            ON clinic_registration_decisions (clinic_registration_id)
            WHERE superseded_at IS NULL
            SQL);

        DB::statement('ALTER TABLE clinic_registrations DROP CONSTRAINT clinic_registrations_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registrations
            ADD CONSTRAINT clinic_registrations_status_check
            CHECK (status IN (
                'draft',
                'submitted',
                'under_review',
                'correction_requested',
                'approved',
                'rejected',
                'provisioned',
                'cancelled',
                'expired'
            ))
            SQL);
        DB::statement('DROP INDEX clinic_registrations_one_active_per_platform_identity');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_one_active_per_platform_identity
            ON clinic_registrations (platform_identity_id)
            WHERE status IN ('draft', 'submitted', 'under_review', 'correction_requested', 'approved')
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clinic_registrations_one_active_per_platform_identity');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_one_active_per_platform_identity
            ON clinic_registrations (platform_identity_id)
            WHERE status IN ('draft', 'submitted')
            SQL);
        DB::statement('ALTER TABLE clinic_registrations DROP CONSTRAINT IF EXISTS clinic_registrations_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registrations
            ADD CONSTRAINT clinic_registrations_status_check
            CHECK (status IN ('draft', 'submitted', 'provisioned', 'cancelled', 'expired'))
            SQL);
        Schema::dropIfExists('clinic_registration_decisions');
    }
};
