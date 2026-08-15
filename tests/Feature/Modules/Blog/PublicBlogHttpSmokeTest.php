<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Blog;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PublicBlogHttpSmokeTest extends TestCase
{
    public const string TENANT = '00000000-0000-4000-8000-000000000001';

    private const string WEBSITE = '00000000-0000-4000-8000-000000000011';

    private const string POST = '00000000-0000-4000-8000-000000000021';

    private HttpSmokeBlogEntitlements $entitlements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema();
        $this->entitlements = new HttpSmokeBlogEntitlements(true);
        $context = new PublicSiteContext('https', 'clinic.test', '', self::WEBSITE, self::TENANT);
        app()->instance(PublicSiteContextFactoryInterface::class, new FixedBlogSiteContext($context));
        app()->instance(BlogAuthorization::class, new BlogAuthorization($this->entitlements, DB::connection()));
        $this->fixtures();
    }

    public function test_published_listing_and_article_are_delivered_over_real_http(): void
    {
        $this->get('https://clinic.test/blog')
            ->assertOk()
            ->assertSee('Health Articles')
            ->assertSee('Panduan Kesihatan')
            ->assertSee('/blog/panduan-kesihatan', false);

        $this->get('https://clinic.test/blog/panduan-kesihatan')
            ->assertOk()
            ->assertSee('<h1>Panduan Kesihatan</h1>', false)
            ->assertSee('Article', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('<p>Kandungan diterbitkan.</p>', false);
    }

    public function test_draft_slug_is_not_public(): void
    {
        DB::table('blog_posts')->where('id', self::POST)->update(['status' => 'draft']);

        $this->get('https://clinic.test/blog/panduan-kesihatan')->assertNotFound();
    }

    public function test_downgrade_disables_public_blog_without_deleting_content(): void
    {
        $this->entitlements->enabled = false;

        $this->get('https://clinic.test/blog')->assertNotFound();
        self::assertSame(1, DB::table('blog_posts')->count());
        self::assertSame(1, DB::table('blog_post_publications')->count());
    }

    private function fixtures(): void
    {
        DB::table('websites')->insert(['id' => self::WEBSITE, 'tenant_id' => self::TENANT, 'clinic_name' => 'Klinik Aman', 'template_id' => 'SYIFA_CARE', 'lifecycle' => 'published']);
        $snapshot = ['slug' => 'panduan-kesihatan', 'title' => 'Panduan Kesihatan', 'excerpt' => 'Ringkasan.', 'body_html' => '<p>Kandungan diterbitkan.</p>', 'category' => 'Kesihatan', 'author_name' => 'Dr. Aminah', 'published_at' => '2026-08-15T02:00:00Z', 'last_changed_at' => '2026-08-15T03:00:00Z', 'meta_title' => 'Panduan Kesihatan', 'meta_description' => 'Panduan kesihatan klinik.', 'canonical_url' => null, 'robots_directive' => 'index,follow', 'open_graph_title' => 'Panduan Kesihatan', 'open_graph_description' => 'Panduan kesihatan klinik.', 'featured_image_asset_id' => null, 'featured_image_alt_text' => null];
        DB::table('blog_posts')->insert(['id' => self::POST, 'tenant_id' => self::TENANT, 'website_id' => self::WEBSITE, 'slug' => 'panduan-kesihatan', 'title' => 'Panduan Kesihatan', 'excerpt' => 'Ringkasan.', 'category' => 'Kesihatan', 'author_name' => 'Dr. Aminah', 'featured_image_asset_id' => null, 'status' => 'published']);
        DB::table('blog_post_publications')->insert(['id' => '00000000-0000-4000-8000-000000000031', 'blog_post_id' => self::POST, 'website_id' => self::WEBSITE, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'published_at' => '2026-08-15 02:00:00', 'withdrawn_at' => null]);
    }

    private function schema(): void
    {
        Schema::create('websites', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id'), $t->string('clinic_name'), $t->string('template_id'), $t->string('lifecycle')]);
        Schema::create('blog_posts', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id'), $t->uuid('website_id'), $t->string('slug'), $t->string('title'), $t->text('excerpt'), $t->string('category'), $t->string('author_name'), $t->uuid('featured_image_asset_id')->nullable(), $t->string('featured_image_alt_text')->nullable(), $t->string('status')]);
        Schema::create('blog_post_publications', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('blog_post_id'), $t->uuid('website_id'), $t->json('snapshot'), $t->timestamp('published_at'), $t->timestamp('withdrawn_at')->nullable()]);
    }
}

final readonly class FixedBlogSiteContext implements PublicSiteContextFactoryInterface
{
    public function __construct(private PublicSiteContext $context) {}

    public function forHost(string $host): ?PublicSiteContext
    {
        return $host === 'clinic.test' ? $this->context : null;
    }
}

final class HttpSmokeBlogEntitlements implements SubscriptionEntitlementLookupInterface
{
    public function __construct(public bool $enabled) {}

    public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
    {
        return $this->enabled && $tenantId === PublicBlogHttpSmokeTest::TENANT && $capabilityKey === 'website.blog.manage';
    }

    public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
    {
        return $this->hasCapability($tenantId, 'website.blog.manage', $effectiveDateTime) ? ['website.blog.manage'] : [];
    }
}
