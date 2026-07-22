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
        Schema::create('website_published_snapshots', function (Blueprint $table): void {
            $table->uuid('publication_id')->primary();
            $table->uuid('website_id');
            $table->unsignedBigInteger('published_version');
            $table->unsignedBigInteger('source_website_version');
            $table->timestampTz('published_at', 6);
            $table->uuid('published_by');
            $table->string('template_id', 64);
            $table->string('clinic_name', 200);
            $table->string('tagline', 240)->nullable();
            $table->char('primary_color', 7);
            $table->char('secondary_color', 7);
            $table->uuid('logo_asset_id')->nullable();
            $table->uuid('favicon_asset_id')->nullable();
            $table->string('contact_email', 254);
            $table->string('contact_phone', 40);
            $table->string('address', 500);
            foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
                $table->string($channel.'_url', 2048)->nullable();
            }
            $table->string('meta_title', 60);
            $table->string('meta_description', 160);
            $table->string('meta_keywords', 255)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots_directive', 32);
            $table->string('open_graph_title', 60);
            $table->string('open_graph_description', 160);
            $table->uuid('open_graph_image_asset_id')->nullable();
            $table->boolean('indexing_enabled');
            $table->char('content_fingerprint', 64);
            $table->timestampTz('created_at', 6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unique(['website_id', 'published_version'], 'website_published_snapshots_version_unique');
            $table->index(['website_id', 'published_at'], 'website_published_snapshots_latest_index');
        });

        Schema::create('website_published_snapshot_sections', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('section_id');
            $table->string('section_type', 32);
            $table->unsignedSmallInteger('display_order');
            $table->boolean('enabled');
            $table->primary(['publication_id', 'section_id']);
            $table->foreign('publication_id')->references('publication_id')->on('website_published_snapshots')->cascadeOnDelete();
            $table->unique(['publication_id', 'section_type'], 'website_snapshot_sections_type_unique');
            $table->unique(['publication_id', 'display_order'], 'website_snapshot_sections_order_unique');
        });

        Schema::create('website_published_snapshot_assets', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('asset_id');
            $table->string('storage_key', 1024);
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64);
            $table->primary(['publication_id', 'asset_id']);
            $table->foreign('publication_id')->references('publication_id')->on('website_published_snapshots')->cascadeOnDelete();
        });

        Schema::create('website_publication_history', function (Blueprint $table): void {
            $table->uuid('publication_id')->primary();
            $table->uuid('website_id');
            $table->unsignedBigInteger('published_version');
            $table->timestampTz('published_at', 6);
            $table->uuid('published_by');
            $table->string('result', 32);
            $table->timestampTz('created_at', 6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unique(['website_id', 'published_version'], 'website_publication_history_version_unique');
        });

        DB::statement("ALTER TABLE website_published_snapshots ADD CONSTRAINT website_snapshots_robots_check CHECK (robots_directive IN ('index,follow','index,nofollow','noindex,follow','noindex,nofollow'))");
        DB::statement("ALTER TABLE website_published_snapshots ADD CONSTRAINT website_snapshots_fingerprint_check CHECK (content_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE website_published_snapshot_sections ADD CONSTRAINT website_snapshot_sections_type_check CHECK (section_type IN ('HERO','ABOUT','SERVICES','DOCTORS','TESTIMONIALS','GALLERY','FAQ','CONTACT','BOOKING_CTA'))");
        DB::statement("ALTER TABLE website_published_snapshot_assets ADD CONSTRAINT website_snapshot_assets_mime_check CHECK (mime_type IN ('image/jpeg','image/png','image/webp','image/svg+xml'))");
        DB::statement("ALTER TABLE website_publication_history ADD CONSTRAINT website_publication_history_result_check CHECK (result IN ('published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('website_publication_history');
        Schema::dropIfExists('website_published_snapshot_assets');
        Schema::dropIfExists('website_published_snapshot_sections');
        Schema::dropIfExists('website_published_snapshots');
    }
};
