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
        Schema::create('payment_provider_configurations', function (Blueprint $table): void {
            $table->string('provider_key', 80)->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('verification_passed')->default(false);
            $table->boolean('webhook_configured')->default(false);
            $table->boolean('provider_ready')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz(6);
        });

        DB::table('payment_provider_configurations')->insert([
            ['provider_key' => 'stripe', 'created_at' => now(), 'updated_at' => now()],
            ['provider_key' => 'toyyibpay', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::statement('CREATE UNIQUE INDEX payment_provider_single_default ON payment_provider_configurations (is_default) WHERE is_default = true');
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_provider_key_required CHECK (provider_key IS NOT NULL AND provider_key <> '') NOT VALID");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_attempts DROP CONSTRAINT IF EXISTS payment_attempts_provider_key_required');
        Schema::dropIfExists('payment_provider_configurations');
    }
};
