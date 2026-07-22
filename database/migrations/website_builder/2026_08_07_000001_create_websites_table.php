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
        Schema::create('websites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('template_id', 32);
            $table->string('lifecycle', 32);
            $table->string('clinic_name', 200);
            $table->string('tagline', 240)->nullable();
            $table->char('primary_color', 7);
            $table->char('secondary_color', 7);
            $table->uuid('logo_reference')->nullable();
            $table->uuid('favicon_reference')->nullable();
            $table->string('contact_email', 254);
            $table->string('contact_phone', 40);
            $table->string('address', 500);
            $table->string('facebook_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->string('linkedin_url', 2048)->nullable();
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_template_check CHECK (template_id IN ('SYIFA_ESSENTIAL','SYIFA_CARE','SYIFA_DENTAL','SYIFA_AESTHETIC','SYIFA_SPECIALIST'))");
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_lifecycle_check CHECK (lifecycle IN ('draft','ready_for_review','published','archived'))");
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_primary_color_check CHECK (primary_color ~ '^#[0-9A-F]{6}$')");
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_secondary_color_check CHECK (secondary_color ~ '^#[0-9A-F]{6}$')");
        DB::statement('ALTER TABLE websites ADD CONSTRAINT websites_version_check CHECK (version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
