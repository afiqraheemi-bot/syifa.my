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
        Schema::table('clinics', function (Blueprint $table): void {
            $table->unsignedSmallInteger('appointment_duration_minutes')->nullable();
            $table->unsignedSmallInteger('booking_capacity_per_slot')->nullable();
        });
        DB::statement('ALTER TABLE clinics ADD CONSTRAINT clinics_booking_configuration_pair_check CHECK ((appointment_duration_minutes IS NULL AND booking_capacity_per_slot IS NULL) OR (appointment_duration_minutes IS NOT NULL AND booking_capacity_per_slot IS NOT NULL))');
        DB::statement('ALTER TABLE clinics ADD CONSTRAINT clinics_appointment_duration_check CHECK (appointment_duration_minutes IS NULL OR appointment_duration_minutes IN (15,20,30,45,60))');
        DB::statement('ALTER TABLE clinics ADD CONSTRAINT clinics_booking_capacity_check CHECK (booking_capacity_per_slot IS NULL OR booking_capacity_per_slot BETWEEN 1 AND 10)');
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn(['appointment_duration_minutes', 'booking_capacity_per_slot']);
        });
    }
};
