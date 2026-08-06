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
        Schema::create('clinic_booking_date_overrides', function (Blueprint $table): void {
            $table->uuid('clinic_id');
            $table->date('local_date');
            $table->boolean('is_closed');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->primary(['clinic_id', 'local_date'], 'clinic_booking_date_overrides_primary');
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->index(['local_date', 'clinic_id'], 'clinic_booking_date_overrides_date_index');
        });

        Schema::create('clinic_booking_date_override_intervals', function (Blueprint $table): void {
            $table->uuid('clinic_id');
            $table->date('local_date');
            $table->unsignedSmallInteger('position');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->primary(['clinic_id', 'local_date', 'position'], 'clinic_booking_date_override_intervals_primary');
            $table->foreign(['clinic_id', 'local_date'], 'clinic_booking_date_override_intervals_override_foreign')
                ->references(['clinic_id', 'local_date'])
                ->on('clinic_booking_date_overrides')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE clinic_booking_date_override_intervals ADD CONSTRAINT clinic_booking_date_override_interval_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_booking_date_override_intervals');
        Schema::dropIfExists('clinic_booking_date_overrides');
    }
};
