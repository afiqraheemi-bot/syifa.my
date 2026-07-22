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
        Schema::create('booking_slot_reservation_buckets', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->timestampTz('starts_at_utc', 6);
            $table->timestampTz('ends_at_utc', 6);
            $table->unsignedSmallInteger('capacity_snapshot');
            $table->unsignedSmallInteger('reserved_count');
            $table->timestampsTz(6);
            $table->primary(['tenant_id', 'starts_at_utc', 'ends_at_utc'], 'booking_slot_buckets_primary');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index(['tenant_id', 'starts_at_utc'], 'booking_slot_buckets_tenant_start_index');
        });
        DB::statement('ALTER TABLE booking_slot_reservation_buckets ADD CONSTRAINT booking_slot_buckets_interval_check CHECK (starts_at_utc < ends_at_utc)');
        DB::statement('ALTER TABLE booking_slot_reservation_buckets ADD CONSTRAINT booking_slot_buckets_capacity_check CHECK (capacity_snapshot BETWEEN 1 AND 10 AND reserved_count BETWEEN 0 AND capacity_snapshot)');

        Schema::create('booking_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('booking_id');
            $table->string('event_type', 40);
            $table->string('actor_type', 32);
            $table->uuid('actor_id')->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->jsonb('payload');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->index(['tenant_id', 'booking_id', 'occurred_at'], 'booking_history_tenant_booking_time_index');
        });
        DB::statement("ALTER TABLE booking_history ADD CONSTRAINT booking_history_event_type_check CHECK (event_type IN ('BookingSubmitted','BookingConfirmed','AppointmentRescheduled','BookingCancelled'))");
        DB::statement("ALTER TABLE booking_history ADD CONSTRAINT booking_history_actor_type_check CHECK (actor_type IN ('public_visitor','clinic_owner','super_admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_history');
        Schema::dropIfExists('booking_slot_reservation_buckets');
    }
};
