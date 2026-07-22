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
            $table->string('booking_source', 16)->nullable();
        });
        DB::table('bookings')->whereNull('booking_source')->update(['booking_source' => 'WEBSITE']);
        DB::statement('ALTER TABLE bookings ALTER COLUMN booking_source SET NOT NULL');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_source_check CHECK (booking_source IN ('WEBSITE','WHATSAPP','PHONE','WALK_IN','STAFF'))");
        if (Schema::hasTable('booking_history')) {
            DB::statement("UPDATE booking_history SET payload = jsonb_build_object('source', 'WEBSITE') || payload WHERE event_type = 'BookingSubmitted'");
        }
        // Deliberately no permanent default: every future creation path must state its source.
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_history')) {
            DB::statement("UPDATE booking_history SET payload = payload - 'source' WHERE event_type = 'BookingSubmitted'");
        }
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('booking_source');
        });
    }
};
