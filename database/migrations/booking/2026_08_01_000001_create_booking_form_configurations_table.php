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
        Schema::create('booking_form_configurations', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->boolean('enable_service_selection');
            $table->boolean('enable_doctor_selection');
            $table->boolean('enable_email');
            $table->boolean('enable_branch');
            $table->boolean('enable_notes');
            $table->jsonb('required_fields');
            $table->jsonb('field_order');
            $table->jsonb('field_labels');
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE booking_form_configurations
            ADD CONSTRAINT booking_form_configurations_version_check CHECK (version > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_form_configurations');
    }
};
