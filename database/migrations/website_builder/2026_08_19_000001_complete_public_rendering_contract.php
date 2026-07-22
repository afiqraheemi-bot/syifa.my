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
        Schema::create('website_published_service_items', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('section_id');
            $table->uuid('service_id');
            $table->string('display_name', 160);
            $table->text('short_description')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_featured');

            $table->primary(['publication_id', 'section_id', 'service_id'], 'website_published_service_items_primary');
            $table->unique(['publication_id', 'section_id', 'display_order'], 'website_published_service_items_order_unique');
            $table->foreign(['publication_id', 'section_id'], 'website_published_service_items_content_foreign')->references(['publication_id', 'section_id'])->on('website_published_section_contents')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX website_published_service_items_one_featured ON website_published_service_items (publication_id, section_id) WHERE is_featured');

        Schema::table('website_published_gallery_images', function (Blueprint $table): void {
            $table->string('alt_text', 500)->nullable();
            $table->text('caption')->nullable();
            $table->boolean('decorative')->default(false);
        });

        Schema::create('website_published_contact_projections', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('section_id');
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
                $table->string($channel.'_url', 2048)->nullable();
            }
            $table->string('whatsapp_number', 40)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->primary(['publication_id', 'section_id'], 'website_published_contact_projections_primary');
            $table->foreign(['publication_id', 'section_id'], 'website_published_contact_projections_content_foreign')->references(['publication_id', 'section_id'])->on('website_published_section_contents')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE website_published_contact_projections ADD CONSTRAINT website_published_contact_projections_coordinates_check CHECK ((latitude IS NULL) = (longitude IS NULL) AND (latitude IS NULL OR latitude BETWEEN -90 AND 90) AND (longitude IS NULL OR longitude BETWEEN -180 AND 180))');

        Schema::create('website_published_business_hours', function (Blueprint $table): void {
            $table->uuid('publication_id');
            $table->uuid('section_id');
            $table->unsignedSmallInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');

            $table->primary(['publication_id', 'section_id', 'day_of_week', 'opens_at'], 'website_published_business_hours_primary');
            $table->foreign(['publication_id', 'section_id'], 'website_published_business_hours_contact_foreign')->references(['publication_id', 'section_id'])->on('website_published_contact_projections')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE website_published_business_hours ADD CONSTRAINT website_published_business_hours_values_check CHECK (day_of_week BETWEEN 1 AND 7 AND opens_at < closes_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('website_published_business_hours');
        Schema::dropIfExists('website_published_contact_projections');
        Schema::table('website_published_gallery_images', function (Blueprint $table): void {
            $table->dropColumn(['alt_text', 'caption', 'decorative']);
        });
        Schema::dropIfExists('website_published_service_items');
    }
};
