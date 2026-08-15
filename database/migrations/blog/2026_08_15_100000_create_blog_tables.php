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
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('website_id');
            $table->uuid('author_identity_id');
            $table->string('author_role', 32);
            $table->string('author_name', 160);
            $table->string('title', 200);
            $table->string('slug', 180);
            $table->text('excerpt');
            $table->text('body_html');
            $table->uuid('featured_image_asset_id')->nullable();
            $table->string('featured_image_alt_text', 240)->nullable();
            $table->string('category', 100);
            $table->jsonb('tags')->default('[]');
            $table->string('status', 32);
            $table->string('meta_title', 200);
            $table->string('meta_description', 320);
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots_directive', 32)->default('index,follow');
            $table->string('open_graph_title', 200);
            $table->string('open_graph_description', 320);
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestampTz('scheduled_at', 6)->nullable();
            $table->timestampTz('created_at_domain', 6);
            $table->timestampTz('last_changed_at', 6);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->unique(['website_id', 'slug']);
            $table->index(['tenant_id', 'status', 'last_changed_at']);
            $table->index(['website_id', 'status', 'published_at']);
            $table->foreign(['website_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('websites')->restrictOnDelete();
            $table->foreign('featured_image_asset_id')->references('id')->on('website_assets')->nullOnDelete();
        });
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_status_check CHECK (status IN ('draft','in_review','correction_required','scheduled','published','archived'))");
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_author_role_check CHECK (author_role IN ('clinic_owner','website_designer','super_admin'))");
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_robots_check CHECK (robots_directive IN ('index,follow','noindex,follow','noindex,nofollow'))");
        DB::statement('ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_version_check CHECK (version > 0)');

        Schema::create('blog_post_publications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('blog_post_id');
            $table->uuid('tenant_id');
            $table->uuid('website_id');
            $table->unsignedBigInteger('source_version');
            $table->jsonb('snapshot');
            $table->timestampTz('published_at', 6);
            $table->timestampTz('withdrawn_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['blog_post_id', 'source_version']);
            $table->index(['website_id', 'withdrawn_at', 'published_at'], 'blog_publications_public_index');
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->cascadeOnDelete();
        });

        Schema::create('blog_post_audits', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('blog_post_id');
            $table->uuid('tenant_id');
            $table->uuid('actor_identity_id');
            $table->string('actor_role', 32);
            $table->string('action', 40);
            $table->unsignedBigInteger('post_version');
            $table->jsonb('metadata')->default('{}');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->cascadeOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_audits');
        Schema::dropIfExists('blog_post_publications');
        Schema::dropIfExists('blog_posts');
    }
};
