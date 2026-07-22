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
        Schema::table('website_published_service_references', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false);
        });
        DB::statement('CREATE UNIQUE INDEX website_published_service_references_one_featured ON website_published_service_references (publication_id, section_id) WHERE is_featured');

        Schema::table('website_sections', function (Blueprint $table): void {
            $table->unique(['id', 'website_id'], 'website_sections_id_website_id_unique');
        });

        Schema::create('website_service_section_items', function (Blueprint $table): void {
            $table->uuid('website_id');
            $table->uuid('section_id');
            $table->uuid('service_id');
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_featured')->default(false);

            $table->primary(['website_id', 'service_id'], 'website_service_section_items_primary');
            $table->unique(['website_id', 'section_id', 'display_order'], 'website_service_section_items_order_unique');
            $table->foreign(['section_id', 'website_id'], 'website_service_section_items_section_website_foreign')
                ->references(['id', 'website_id'])
                ->on('website_sections')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE website_service_section_items ADD CONSTRAINT website_service_section_items_order_check CHECK (display_order BETWEEN 1 AND 100)');
        DB::statement('CREATE UNIQUE INDEX website_service_section_items_one_featured ON website_service_section_items (section_id) WHERE is_featured');

        DB::statement(<<<'SQL'
            INSERT INTO website_service_section_items (website_id, section_id, service_id, display_order, is_featured)
            SELECT content.website_id, content.section_id, reference.service_id, reference.display_order, FALSE
            FROM website_published_service_references reference
            JOIN website_published_section_contents content
              ON content.publication_id = reference.publication_id
             AND content.section_id = reference.section_id
            JOIN website_published_snapshots snapshot
              ON snapshot.publication_id = content.publication_id
            WHERE snapshot.published_version = (
                SELECT MAX(latest.published_version)
                FROM website_published_snapshots latest
                WHERE latest.website_id = snapshot.website_id
            )
            ON CONFLICT (website_id, service_id) DO NOTHING
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('website_service_section_items');
        Schema::table('website_sections', function (Blueprint $table): void {
            $table->dropUnique('website_sections_id_website_id_unique');
        });
        Schema::table('website_published_service_references', function (Blueprint $table): void {
            $table->dropColumn('is_featured');
        });
    }
};
