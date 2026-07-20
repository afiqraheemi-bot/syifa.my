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
        Schema::create('clinic_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_identity_id');
            $table->string('status', 32);
            $table->string('clinic_name', 200)->nullable();
            $table->string('clinic_email', 254)->nullable();
            $table->string('clinic_phone', 40)->nullable();
            $table->text('clinic_address')->nullable();
            $table->string('selected_plan_offering_reference', 120)->nullable();
            $table->string('selected_billing_option_reference', 120)->nullable();
            $table->string('commercial_snapshot_version', 64)->nullable();
            $table->string('registration_correlation_reference', 120)->unique();
            $table->string('provisioned_tenant_reference', 120)->nullable()->unique();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('provisioned_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->unsignedBigInteger('version');
            $table->timestampsTz();

            $table->index(['platform_identity_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('clinic_registration_declaration_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clinic_registration_id');
            $table->string('declaration_key', 100);
            $table->string('declaration_version', 64);
            $table->timestampTz('accepted_at');
            $table->timestampsTz();

            $table->foreign('clinic_registration_id')
                ->references('id')
                ->on('clinic_registrations')
                ->cascadeOnDelete();
            $table->unique(['clinic_registration_id', 'declaration_key', 'declaration_version'], 'clinic_registration_declarations_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registrations
            ADD CONSTRAINT clinic_registrations_status_check
            CHECK (status IN ('draft', 'submitted', 'provisioned', 'cancelled', 'expired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_registrations
            ADD CONSTRAINT clinic_registrations_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_one_active_per_platform_identity
            ON clinic_registrations (platform_identity_id)
            WHERE status IN ('draft', 'submitted')
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_registration_declaration_acceptances');
        Schema::dropIfExists('clinic_registrations');
    }
};
