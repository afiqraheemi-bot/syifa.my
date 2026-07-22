<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('website_id');
            $table->string('section_type', 32);
            $table->unsignedSmallInteger('display_order');
            $table->boolean('enabled');
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->index(['website_id', 'enabled', 'display_order'], 'website_sections_enabled_order_index');
        });
        DB::statement("ALTER TABLE website_sections ADD CONSTRAINT website_sections_type_check CHECK (section_type IN ('HERO','ABOUT','SERVICES','DOCTORS','TESTIMONIALS','GALLERY','FAQ','CONTACT','BOOKING_CTA'))");
        DB::statement('ALTER TABLE website_sections ADD CONSTRAINT website_sections_order_check CHECK (display_order BETWEEN 1 AND 9)');
        DB::statement('ALTER TABLE website_sections ADD CONSTRAINT website_sections_version_check CHECK (version > 0)');
        DB::statement('ALTER TABLE website_sections ADD CONSTRAINT website_sections_website_type_unique UNIQUE (website_id, section_type) DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE website_sections ADD CONSTRAINT website_sections_website_order_unique UNIQUE (website_id, display_order) DEFERRABLE INITIALLY DEFERRED');

        DB::transaction(function (): void {
            $types = ['HERO', 'ABOUT', 'SERVICES', 'DOCTORS', 'TESTIMONIALS', 'GALLERY', 'FAQ', 'CONTACT', 'BOOKING_CTA'];
            foreach (DB::table('websites')->select(['id', 'domain_created_at', 'domain_updated_at', 'created_at', 'updated_at'])->get() as $website) {
                foreach ($types as $index => $type) {
                    DB::table('website_sections')->insert([
                        'id' => (string) Str::uuid(), 'website_id' => (string) $website->id, 'section_type' => $type,
                        'display_order' => $index + 1, 'enabled' => true, 'domain_created_at' => $website->domain_created_at,
                        'domain_updated_at' => $website->domain_updated_at, 'version' => 1, 'created_at' => $website->created_at, 'updated_at' => $website->updated_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sections');
    }
};
