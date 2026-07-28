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
        Schema::create('initial_acquisition_checkout_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clinic_registration_id');
            $table->uuid('commercial_offer_id');
            $table->uuid('payment_id');
            $table->string('stage', 32);
            $table->timestampTz('commercial_offer_valid_until', 6);
            $table->string('session_id', 160)->nullable()->unique();
            $table->text('redirect_destination')->nullable();
            $table->timestampTz('session_expires_at', 6)->nullable();
            $table->string('expiry_authority', 32)->nullable();
            $table->timestampsTz(6);

            $table->unique(['clinic_registration_id', 'commercial_offer_id']);
            $table->unique('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE initial_acquisition_checkout_sessions
            ADD CONSTRAINT initial_acquisition_checkout_stage_check
            CHECK (stage IN ('session_pending', 'session_ready'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE initial_acquisition_checkout_sessions
            ADD CONSTRAINT initial_acquisition_checkout_session_consistency_check
            CHECK (
                (
                    stage = 'session_ready'
                    AND session_id IS NOT NULL
                    AND redirect_destination IS NOT NULL
                    AND session_expires_at IS NOT NULL
                    AND expiry_authority IS NOT NULL
                )
                OR
                (
                    stage = 'session_pending'
                    AND session_id IS NULL
                    AND redirect_destination IS NULL
                    AND session_expires_at IS NULL
                    AND expiry_authority IS NULL
                )
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('initial_acquisition_checkout_sessions');
    }
};
