<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_workforce_mfa_enrollments', function (Blueprint $table): void {
            $table->uuid('platform_identity_id')->primary();
            $table->foreign('platform_identity_id')
                ->references('platform_identity_id')
                ->on('platform_workforce_credentials')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->text('encrypted_totp_secret');
            $table->timestampTz('confirmed_at', 6);
            $table->unsignedBigInteger('last_verified_time_step')->nullable();
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_workforce_mfa_enrollments');
    }
};
