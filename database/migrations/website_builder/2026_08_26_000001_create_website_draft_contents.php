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
        DB::statement('ALTER TABLE website_assets ADD CONSTRAINT website_assets_id_website_unique UNIQUE (id, website_id)');

        Schema::create('website_drafts', function (Blueprint $table): void {
            $table->uuid('website_id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->foreign(['website_id', 'tenant_id'], 'website_drafts_website_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('websites')->cascadeOnDelete();
            $table->index(['tenant_id', 'website_id'], 'website_drafts_tenant_website_index');
        });

        Schema::create('website_draft_section_contents', function (Blueprint $table): void {
            $table->uuid('website_id');
            $table->uuid('section_id');
            $table->string('section_type', 32);
            $table->primary(['website_id', 'section_id']);
            $table->unique(['website_id', 'section_type'], 'website_draft_content_type_unique');
            $table->foreign('website_id')->references('website_id')->on('website_drafts')->cascadeOnDelete();
            $table->foreign(['section_id', 'website_id'], 'website_draft_content_section_website_foreign')
                ->references(['id', 'website_id'])->on('website_sections')->cascadeOnDelete();
        });

        $this->singleton('website_draft_hero_contents', function (Blueprint $table): void {
            $table->string('headline', 160)->nullable();
            $table->string('subheadline', 500)->nullable();
            $table->string('primary_cta_label', 80)->nullable();
            $table->string('primary_cta_target', 2048)->nullable();
            $table->string('secondary_cta_label', 80)->nullable();
            $table->string('secondary_cta_target', 2048)->nullable();
            $table->uuid('hero_image_asset_id')->nullable();
            $table->foreign(['hero_image_asset_id', 'website_id'], 'website_draft_hero_asset_foreign')
                ->references(['id', 'website_id'])->on('website_assets');
        });
        $this->singleton('website_draft_about_contents', function (Blueprint $table): void {
            $table->string('heading', 160)->nullable();
            $table->text('description')->nullable();
            $table->uuid('image_asset_id')->nullable();
            $table->foreign(['image_asset_id', 'website_id'], 'website_draft_about_asset_foreign')
                ->references(['id', 'website_id'])->on('website_assets');
        });
        $this->singleton('website_draft_booking_cta_contents', function (Blueprint $table): void {
            $table->string('heading', 160)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('button_label', 80)->nullable();
        });

        $this->ordered('website_draft_doctor_profiles', function (Blueprint $table): void {
            $table->uuid('profile_id');
            $table->string('name', 160);
            $table->string('professional_title', 160)->nullable();
            $table->boolean('visible');
            $table->uuid('photo_asset_id')->nullable();
            $table->foreign(['photo_asset_id', 'website_id'], 'website_draft_doctor_asset_foreign')
                ->references(['id', 'website_id'])->on('website_assets');
        });
        $this->ordered('website_draft_testimonials', function (Blueprint $table): void {
            $table->uuid('testimonial_id');
            $table->text('quote');
            $table->string('author_name', 160);
            $table->boolean('featured');
        });
        $this->ordered('website_draft_gallery_images', function (Blueprint $table): void {
            $table->uuid('gallery_image_id');
            $table->uuid('asset_id');
            $table->string('alt_text', 500)->nullable();
            $table->string('caption', 1000)->nullable();
            $table->boolean('decorative');
            $table->foreign(['asset_id', 'website_id'], 'website_draft_gallery_asset_foreign')
                ->references(['id', 'website_id'])->on('website_assets');
        });
        $this->ordered('website_draft_faq_entries', function (Blueprint $table): void {
            $table->uuid('faq_entry_id');
            $table->string('question', 500);
            $table->text('answer');
        });

        DB::statement('ALTER TABLE website_drafts ADD CONSTRAINT website_drafts_version_check CHECK (version >= 1)');
        DB::statement("ALTER TABLE website_draft_section_contents ADD CONSTRAINT website_draft_content_type_check CHECK (section_type IN ('HERO','ABOUT','SERVICES','DOCTORS','TESTIMONIALS','GALLERY','FAQ','CONTACT','BOOKING_CTA'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_website_draft_for_new_website() RETURNS trigger AS $$
            BEGIN
                INSERT INTO website_drafts (website_id, tenant_id, version, created_at, updated_at)
                VALUES (NEW.id, NEW.tenant_id, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER websites_create_draft
            AFTER INSERT ON websites
            FOR EACH ROW EXECUTE FUNCTION create_website_draft_for_new_website();

            CREATE OR REPLACE FUNCTION create_website_draft_section_for_new_section() RETURNS trigger AS $$
            BEGIN
                INSERT INTO website_draft_section_contents (website_id, section_id, section_type)
                VALUES (NEW.website_id, NEW.id, NEW.section_type);
                IF NEW.section_type = 'HERO' THEN
                    INSERT INTO website_draft_hero_contents (website_id, section_id) VALUES (NEW.website_id, NEW.id);
                ELSIF NEW.section_type = 'ABOUT' THEN
                    INSERT INTO website_draft_about_contents (website_id, section_id) VALUES (NEW.website_id, NEW.id);
                ELSIF NEW.section_type = 'BOOKING_CTA' THEN
                    INSERT INTO website_draft_booking_cta_contents (website_id, section_id) VALUES (NEW.website_id, NEW.id);
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER website_sections_create_draft_content
            AFTER INSERT ON website_sections
            FOR EACH ROW EXECUTE FUNCTION create_website_draft_section_for_new_section();
            SQL);
        DB::statement('INSERT INTO website_drafts (website_id, tenant_id, version, created_at, updated_at) SELECT id, tenant_id, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM websites');
        DB::statement('INSERT INTO website_draft_section_contents (website_id, section_id, section_type) SELECT website_id, id, section_type FROM website_sections');
        DB::statement("INSERT INTO website_draft_hero_contents (website_id, section_id) SELECT website_id, section_id FROM website_draft_section_contents WHERE section_type = 'HERO'");
        DB::statement("INSERT INTO website_draft_about_contents (website_id, section_id) SELECT website_id, section_id FROM website_draft_section_contents WHERE section_type = 'ABOUT'");
        DB::statement("INSERT INTO website_draft_booking_cta_contents (website_id, section_id) SELECT website_id, section_id FROM website_draft_section_contents WHERE section_type = 'BOOKING_CTA'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS website_sections_create_draft_content ON website_sections; DROP FUNCTION IF EXISTS create_website_draft_section_for_new_section(); DROP TRIGGER IF EXISTS websites_create_draft ON websites; DROP FUNCTION IF EXISTS create_website_draft_for_new_website();');
        foreach ([
            'website_draft_faq_entries', 'website_draft_gallery_images', 'website_draft_testimonials',
            'website_draft_doctor_profiles', 'website_draft_booking_cta_contents',
            'website_draft_about_contents', 'website_draft_hero_contents',
            'website_draft_section_contents', 'website_drafts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('ALTER TABLE website_assets DROP CONSTRAINT IF EXISTS website_assets_id_website_unique');
    }

    private function singleton(string $name, callable $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns): void {
            $this->identity($table);
            $columns($table);
            $table->primary(['website_id', 'section_id']);
            $table->foreign(['website_id', 'section_id'])->references(['website_id', 'section_id'])->on('website_draft_section_contents')->cascadeOnDelete();
        });
    }

    private function ordered(string $name, callable $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns): void {
            $this->identity($table);
            $table->unsignedSmallInteger('display_order');
            $columns($table);
            $table->primary(['website_id', 'section_id', 'display_order']);
            $table->foreign(['website_id', 'section_id'])->references(['website_id', 'section_id'])->on('website_draft_section_contents')->cascadeOnDelete();
        });
    }

    private function identity(Blueprint $table): void
    {
        $table->uuid('website_id');
        $table->uuid('section_id');
    }
};
