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
        Schema::create('clinics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('timezone', 64);
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);

            $table->foreign('tenant_id', 'clinics_tenant_id_foreign')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();
        });

        Schema::create('clinic_operating_intervals', function (Blueprint $table): void {
            $table->uuid('clinic_id');
            $table->unsignedSmallInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');

            $table->primary(['clinic_id', 'day_of_week', 'opens_at'], 'clinic_operating_intervals_primary');
            $table->foreign('clinic_id', 'clinic_operating_intervals_clinic_id_foreign')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE clinics ADD CONSTRAINT clinics_version_check CHECK (version > 0)');
        DB::statement('ALTER TABLE clinic_operating_intervals ADD CONSTRAINT clinic_operating_intervals_day_check CHECK (day_of_week BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE clinic_operating_intervals ADD CONSTRAINT clinic_operating_intervals_time_check CHECK (opens_at < closes_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_operating_intervals');
        Schema::dropIfExists('clinics');
    }
};
