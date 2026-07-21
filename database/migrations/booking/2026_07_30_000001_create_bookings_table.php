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
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('clinic_id');
            $table->string('booking_reference', 120)->unique();
            $table->string('status', 32);
            $table->string('patient_name', 200);
            $table->string('patient_phone', 40);
            $table->string('patient_email', 254)->nullable();
            $table->date('appointment_on');
            $table->time('appointment_time');
            $table->text('notes')->nullable();
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);

            $table->index('tenant_id');
            $table->index('clinic_id');
            $table->index(['tenant_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_status_check
            CHECK (status IN ('submitted'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_version_check CHECK (version > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
