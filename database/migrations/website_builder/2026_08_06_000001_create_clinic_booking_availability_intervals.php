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
        Schema::create('clinic_booking_availability_intervals', function (Blueprint $table): void {
            $table->uuid('clinic_id');
            $table->unsignedSmallInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');

            $table->primary(
                ['clinic_id', 'day_of_week', 'starts_at'],
                'clinic_booking_availability_primary',
            );
            $table->foreign('clinic_id', 'clinic_booking_availability_clinic_foreign')
                ->references('id')
                ->on('clinics')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE clinic_booking_availability_intervals ADD CONSTRAINT clinic_booking_availability_day_check CHECK (day_of_week BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE clinic_booking_availability_intervals ADD CONSTRAINT clinic_booking_availability_time_check CHECK (starts_at < ends_at)');
        DB::statement(<<<'SQL'
            INSERT INTO clinic_booking_availability_intervals (clinic_id, day_of_week, starts_at, ends_at)
            SELECT clinic_id, day_of_week, opens_at, closes_at
            FROM clinic_operating_intervals
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_booking_availability_intervals');
    }
};
