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
        Schema::create('website_seo_configurations', function (Blueprint $table): void {
            $table->uuid('website_id')->primary();
            $table->string('meta_title', 60);
            $table->string('meta_description', 160);
            $table->string('meta_keywords', 255)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots_directive', 32);
            $table->string('open_graph_title', 60);
            $table->string('open_graph_description', 160);
            $table->uuid('open_graph_image_reference')->nullable();
            $table->boolean('indexing_enabled');
            $table->timestampTz('domain_created_at', 6);
            $table->timestampTz('domain_updated_at', 6);
            $table->unsignedBigInteger('version');
            $table->timestampsTz(6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE website_seo_configurations ADD CONSTRAINT website_seo_robots_check CHECK (robots_directive IN ('index,follow','index,nofollow','noindex,follow','noindex,nofollow'))");
        DB::statement("ALTER TABLE website_seo_configurations ADD CONSTRAINT website_seo_canonical_https_check CHECK (canonical_url IS NULL OR canonical_url ~ '^https://')");
        DB::statement('ALTER TABLE website_seo_configurations ADD CONSTRAINT website_seo_version_check CHECK (version > 0)');
        DB::statement("ALTER TABLE website_seo_configurations ADD CONSTRAINT website_seo_plain_text_check CHECK (position('<' in meta_title) = 0 AND position('>' in meta_title) = 0 AND position('<' in meta_description) = 0 AND position('>' in meta_description) = 0 AND position('<' in open_graph_title) = 0 AND position('>' in open_graph_title) = 0 AND position('<' in open_graph_description) = 0 AND position('>' in open_graph_description) = 0)");

        DB::transaction(function (): void {
            foreach (DB::table('websites')->select(['id', 'clinic_name', 'tagline', 'domain_created_at', 'domain_updated_at', 'created_at', 'updated_at'])->get() as $website) {
                $title = $this->defaultText((string) $website->clinic_name, 60, 'Clinic Website');
                $tagline = is_string($website->tagline) ? $website->tagline : (string) $website->clinic_name;
                $description = $this->defaultText($tagline, 160, $title);
                DB::table('website_seo_configurations')->insert([
                    'website_id' => (string) $website->id, 'meta_title' => $title, 'meta_description' => $description,
                    'meta_keywords' => null, 'canonical_url' => null, 'robots_directive' => 'index,follow',
                    'open_graph_title' => $title, 'open_graph_description' => $description, 'open_graph_image_reference' => null,
                    'indexing_enabled' => true, 'domain_created_at' => $website->domain_created_at, 'domain_updated_at' => $website->domain_updated_at,
                    'version' => 1, 'created_at' => $website->created_at, 'updated_at' => $website->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_seo_configurations');
    }

    private function defaultText(string $value, int $maximum, string $fallback): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $plain) === 1) {
            $plain = $fallback;
        }

        return mb_substr($plain, 0, $maximum);
    }
};
