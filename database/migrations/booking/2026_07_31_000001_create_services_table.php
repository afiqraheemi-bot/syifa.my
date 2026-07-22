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
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sort_order');
            $table->string('status', 16);
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);

            $table->index('tenant_id');
            $table->index('status');
            $table->index('sort_order');
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'name']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE services
            ADD CONSTRAINT services_status_check
            CHECK (status IN ('active', 'inactive'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE services
            ADD CONSTRAINT services_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE services
            ADD CONSTRAINT services_duration_minutes_check
            CHECK (duration_minutes IS NULL OR duration_minutes > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
