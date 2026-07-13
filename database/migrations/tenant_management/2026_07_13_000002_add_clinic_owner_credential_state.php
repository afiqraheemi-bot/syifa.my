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
        Schema::table('clinic_owner_authorities', function (Blueprint $table): void {
            $table->string('password_hash', 255)->nullable();
            $table->string('email_verification_status', 16)->default('unverified');
            $table->timestampTz('email_verified_at')->nullable();
            $table->unsignedSmallInteger('failed_attempt_count')->default(0);
            $table->timestampTz('lockout_until')->nullable();
            $table->unsignedBigInteger('credential_version')->default(0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE clinic_owner_authorities
            ADD CONSTRAINT clinic_owner_authorities_email_verification_check
            CHECK (
                (email_verification_status = 'unverified' AND email_verified_at IS NULL)
                OR (email_verification_status = 'verified' AND email_verified_at IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_owner_authorities
            ADD CONSTRAINT clinic_owner_authorities_attempt_state_check
            CHECK (
                (failed_attempt_count BETWEEN 0 AND 4 AND lockout_until IS NULL)
                OR (failed_attempt_count = 5 AND lockout_until IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE clinic_owner_authorities
            ADD CONSTRAINT clinic_owner_authorities_credential_version_check
            CHECK (
                (password_hash IS NULL AND credential_version = 0)
                OR (password_hash IS NOT NULL AND credential_version > 0)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clinic_owner_authorities DROP CONSTRAINT IF EXISTS clinic_owner_authorities_email_verification_check');
        DB::statement('ALTER TABLE clinic_owner_authorities DROP CONSTRAINT IF EXISTS clinic_owner_authorities_attempt_state_check');
        DB::statement('ALTER TABLE clinic_owner_authorities DROP CONSTRAINT IF EXISTS clinic_owner_authorities_credential_version_check');

        Schema::table('clinic_owner_authorities', function (Blueprint $table): void {
            $table->dropColumn([
                'password_hash',
                'email_verification_status',
                'email_verified_at',
                'failed_attempt_count',
                'lockout_until',
                'credential_version',
            ]);
        });
    }
};
