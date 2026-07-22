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
        DB::statement('ALTER TABLE websites ADD CONSTRAINT websites_id_tenant_unique UNIQUE (id, tenant_id)');
        Schema::create('website_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('website_id');
            $table->uuid('tenant_id');
            $table->string('storage_key', 1024)->unique();
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64);
            $table->string('status', 16);
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['website_id', 'tenant_id'], 'website_assets_website_tenant_foreign')->references(['id', 'tenant_id'])->on('websites')->cascadeOnDelete();
            $table->index(['website_id', 'status'], 'website_assets_website_status_index');
        });
        DB::statement("ALTER TABLE website_assets ADD CONSTRAINT website_assets_mime_check CHECK (mime_type IN ('image/jpeg','image/png','image/webp','image/svg+xml'))");
        DB::statement("ALTER TABLE website_assets ADD CONSTRAINT website_assets_status_check CHECK (status IN ('pending','available','archived'))");
        DB::statement('ALTER TABLE website_assets ADD CONSTRAINT website_assets_size_check CHECK (file_size_bytes > 0)');
        DB::statement('ALTER TABLE website_assets ADD CONSTRAINT website_assets_dimensions_check CHECK ((width IS NULL OR width > 0) AND (height IS NULL OR height > 0))');
        DB::statement("ALTER TABLE website_assets ADD CONSTRAINT website_assets_checksum_check CHECK (checksum ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE website_assets ADD CONSTRAINT website_assets_version_check CHECK (version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('website_assets');
        DB::statement('ALTER TABLE websites DROP CONSTRAINT IF EXISTS websites_id_tenant_unique');
    }
};
