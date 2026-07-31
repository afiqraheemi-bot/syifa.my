<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE booking_history DROP CONSTRAINT booking_history_event_type_check');
        DB::statement("ALTER TABLE booking_history ADD CONSTRAINT booking_history_event_type_check CHECK (event_type IN ('BookingSubmitted','BookingConfirmed','AppointmentRescheduled','BookingCancelled','BookingCompleted'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE booking_history DROP CONSTRAINT booking_history_event_type_check');
        DB::statement("ALTER TABLE booking_history ADD CONSTRAINT booking_history_event_type_check CHECK (event_type IN ('BookingSubmitted','BookingConfirmed','AppointmentRescheduled','BookingCancelled'))");
    }
};
