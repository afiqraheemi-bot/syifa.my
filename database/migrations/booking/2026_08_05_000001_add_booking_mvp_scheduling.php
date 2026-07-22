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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->time('local_end_time')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestampTz('starts_at_utc', 6)->nullable();
            $table->timestampTz('ends_at_utc', 6)->nullable();
            $table->unsignedSmallInteger('appointment_duration_minutes')->nullable();
            $table->index(['tenant_id', 'appointment_on', 'status'], 'bookings_tenant_date_status_index');
            $table->index(['tenant_id', 'starts_at_utc'], 'bookings_tenant_starts_at_index');
        });
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_status_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('submitted','confirmed','cancelled','completed'))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_schedule_snapshot_check CHECK ((local_end_time IS NULL AND timezone IS NULL AND starts_at_utc IS NULL AND ends_at_utc IS NULL AND appointment_duration_minutes IS NULL) OR (local_end_time IS NOT NULL AND timezone IS NOT NULL AND starts_at_utc IS NOT NULL AND ends_at_utc IS NOT NULL AND appointment_duration_minutes IS NOT NULL AND starts_at_utc < ends_at_utc))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_status_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('submitted'))");
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_tenant_date_status_index');
            $table->dropIndex('bookings_tenant_starts_at_index');
            $table->dropColumn(['local_end_time', 'timezone', 'starts_at_utc', 'ends_at_utc', 'appointment_duration_minutes']);
        });
    }
};
