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
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32);
            $table->unsignedBigInteger('version');
            $table->timestampTz('provisioned_at');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('reactivated_at')->nullable();
            $table->timestampTz('offboarding_started_at')->nullable();
            $table->timestampTz('deleted_or_anonymized_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('clinic_owner_authorities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('clinic_owner_identity_id');
            $table->string('email', 254);
            $table->string('name', 120);
            $table->string('authority_status', 16);
            $table->timestampTz('established_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_status_check
            CHECK (status IN ('provisioning', 'active', 'suspended', 'reactivated', 'offboarding', 'deleted', 'anonymized'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_owner_authorities
            ADD CONSTRAINT clinic_owner_authorities_status_check
            CHECK (authority_status IN ('active', 'revoked'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_owner_authorities
            ADD CONSTRAINT clinic_owner_authorities_revocation_check
            CHECK (
                (authority_status = 'active' AND revoked_at IS NULL)
                OR (authority_status = 'revoked' AND revoked_at IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_owner_authorities_one_active_per_tenant
            ON clinic_owner_authorities (tenant_id)
            WHERE authority_status = 'active'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_owner_authorities');
        Schema::dropIfExists('tenants');
    }
};
