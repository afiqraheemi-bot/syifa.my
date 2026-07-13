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
        Schema::create('platform_workforce_credentials', function (Blueprint $table): void {
            $table->uuid('platform_identity_id')->primary();
            $table->string('normalized_email', 254)->unique();
            $table->string('password_hash', 255);
            $table->string('email_verification_status', 16);
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('account_status', 16);
            $table->unsignedSmallInteger('failed_attempt_count')->default(0);
            $table->timestampTz('lockout_until')->nullable();
            $table->unsignedBigInteger('version');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_email_verification_check
            CHECK (
                (email_verification_status = 'unverified' AND email_verified_at IS NULL)
                OR (email_verification_status = 'verified' AND email_verified_at IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_account_status_check
            CHECK (account_status IN ('active', 'suspended', 'revoked'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_attempt_state_check
            CHECK (
                (failed_attempt_count BETWEEN 0 AND 4 AND lockout_until IS NULL)
                OR (failed_attempt_count = 5 AND lockout_until IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_normalized_email_check
            CHECK (normalized_email = lower(btrim(normalized_email)))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_workforce_credentials');
    }
};
