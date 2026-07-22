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
        Schema::create('website_published_section_contents', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('website_id');
            $table->uuid('section_id');
            $table->string('section_type', 32);
            $table->unsignedBigInteger('published_version');
            $table->char('content_fingerprint', 64);
            $table->boolean('renderable');
            $table->timestampTz('created_at', 6);
            $table->unsignedBigInteger('version');
            $table->primary(['publication_id', 'section_id']);
            $table->foreign('publication_id')->references('publication_id')->on('website_published_snapshots')->cascadeOnDelete();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('website_sections')->cascadeOnDelete();
            $table->unique(['publication_id', 'section_type'], 'website_published_content_type_unique');
            $table->index(['website_id', 'published_version'], 'website_published_content_version_index');
        });

        $this->singleton('website_published_hero_contents', function (Blueprint $table): void {
            $table->string('headline', 160)->nullable();
            $table->string('subheadline', 500)->nullable();
            $table->string('primary_cta_label', 80)->nullable();
            $table->string('primary_cta_target', 2048)->nullable();
            $table->string('secondary_cta_label', 80)->nullable();
            $table->string('secondary_cta_target', 2048)->nullable();
            $table->uuid('hero_image_asset_id')->nullable();
            $table->foreign(['publication_id', 'hero_image_asset_id'])->references(['publication_id', 'asset_id'])->on('website_published_snapshot_assets');
        });
        $this->singleton('website_published_about_contents', function (Blueprint $table): void {
            $table->string('heading', 160)->nullable();
            $table->text('description')->nullable();
            $table->uuid('image_asset_id')->nullable();
            $table->foreign(['publication_id', 'image_asset_id'])->references(['publication_id', 'asset_id'])->on('website_published_snapshot_assets');
        });
        $this->singleton('website_published_contact_contents', function (Blueprint $table): void {
            $table->string('contact_email', 254);
            $table->string('contact_phone', 40);
            $table->string('address', 500);
            foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
                $table->string($channel.'_url', 2048)->nullable();
            }
        });
        $this->singleton('website_published_booking_cta_contents', function (Blueprint $table): void {
            $table->string('heading', 160)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('button_label', 80)->nullable();
        });

        $this->ordered('website_published_service_references', function (Blueprint $table): void {
            $table->uuid('service_id');
        });
        $this->ordered('website_published_doctor_profiles', function (Blueprint $table): void {
            $table->uuid('profile_id');
            $table->string('name', 160);
            $table->string('professional_title', 160)->nullable();
            $table->boolean('visible');
            $table->uuid('photo_asset_id')->nullable();
            $table->foreign(['publication_id', 'photo_asset_id'])->references(['publication_id', 'asset_id'])->on('website_published_snapshot_assets');
        });
        $this->ordered('website_published_testimonials', function (Blueprint $table): void {
            $table->uuid('testimonial_id');
            $table->text('quote');
            $table->string('author_name', 160);
            $table->boolean('featured');
        });
        $this->ordered('website_published_gallery_images', function (Blueprint $table): void {
            $table->uuid('gallery_image_id');
            $table->uuid('asset_id');
            $table->foreign(['publication_id', 'asset_id'])->references(['publication_id', 'asset_id'])->on('website_published_snapshot_assets');
        });
        $this->ordered('website_published_faq_entries', function (Blueprint $table): void {
            $table->uuid('faq_entry_id');
            $table->string('question', 500);
            $table->text('answer');
        });

        DB::statement("ALTER TABLE website_published_section_contents ADD CONSTRAINT website_published_content_type_check CHECK (section_type IN ('HERO','ABOUT','SERVICES','DOCTORS','TESTIMONIALS','GALLERY','FAQ','CONTACT','BOOKING_CTA'))");
        DB::statement("ALTER TABLE website_published_section_contents ADD CONSTRAINT website_published_content_fingerprint_check CHECK (content_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE website_published_section_contents ADD CONSTRAINT website_published_content_version_check CHECK (version = 1)');
    }

    public function down(): void
    {
        foreach (['website_published_faq_entries', 'website_published_gallery_images', 'website_published_testimonials', 'website_published_doctor_profiles', 'website_published_service_references', 'website_published_booking_cta_contents', 'website_published_contact_contents', 'website_published_about_contents', 'website_published_hero_contents', 'website_published_section_contents'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function singleton(string $name, callable $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns): void {
            $this->contentIdentity($table);
            $columns($table);
            $table->primary(['publication_id', 'section_id']);
            $table->foreign(['publication_id', 'section_id'])->references(['publication_id', 'section_id'])->on('website_published_section_contents')->cascadeOnDelete();
        });
    }

    private function ordered(string $name, callable $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns): void {
            $this->contentIdentity($table);
            $table->unsignedSmallInteger('display_order');
            $columns($table);
            $table->primary(['publication_id', 'section_id', 'display_order']);
            $table->foreign(['publication_id', 'section_id'])->references(['publication_id', 'section_id'])->on('website_published_section_contents')->cascadeOnDelete();
        });
    }

    private function contentIdentity(Blueprint $table): void
    {
        $table->uuid('publication_id');
        $table->uuid('section_id');
    }
};
