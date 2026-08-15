<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Blog;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

final class PublicBlogTemplateRenderingTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function templates(): iterable
    {
        yield 'Essential' => ['SYIFA_ESSENTIAL'];
        yield 'Care' => ['SYIFA_CARE'];
        yield 'Dental' => ['SYIFA_DENTAL'];
        yield 'Aesthetic' => ['SYIFA_AESTHETIC'];
        yield 'Specialist' => ['SYIFA_SPECIALIST'];
    }

    #[DataProvider('templates')]
    public function test_each_template_renders_accessible_article_and_complete_seo(string $template): void
    {
        $html = view('public-website.blog.show', [
            'context' => new PublicSiteContext('https', 'klinik-aman.syifa.my', '', $this->uuid(11), $this->uuid(1)),
            'website' => $this->website($template),
            'post' => $this->article(),
        ])->render();

        self::assertStringContainsString('data-template="'.strtolower(str_replace('_', '-', $template)).'"', $html);
        self::assertSame(1, preg_match_all('/<h1\b/i', $html));
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        self::assertStringContainsString('https://schema.org', $html);
        self::assertStringContainsString('BreadcrumbList', $html);
        self::assertStringContainsString('alt="Pasukan klinik memberikan penerangan kesihatan"', $html);
        self::assertStringContainsString('href="/booking"', $html);
        self::assertStringContainsString('data-blog-share', $html);
        self::assertStringContainsString('https://wa.me/?text=', $html);
        self::assertStringContainsString('facebook.com/sharer/sharer.php', $html);
        self::assertStringContainsString('twitter.com/intent/tweet', $html);
        self::assertStringContainsString('linkedin.com/sharing/share-offsite', $html);
        self::assertStringContainsString('data-blog-copy-link', $html);
    }

    public function test_cross_tenant_canonical_is_replaced_with_host_scoped_url(): void
    {
        $post = $this->article();
        $post->canonical_url = 'https://tenant-lain.syifa.my/blog/rahsia';
        $html = view('public-website.blog.show', [
            'context' => new PublicSiteContext('https', 'klinik-aman.syifa.my', '', $this->uuid(11), $this->uuid(1)),
            'website' => $this->website('SYIFA_CARE'),
            'post' => $post,
        ])->render();

        self::assertStringContainsString('<link rel="canonical" href="https://klinik-aman.syifa.my/blog/panduan-kesihatan">', $html);
        self::assertStringNotContainsString('tenant-lain.syifa.my', $html);
    }

    public function test_preview_article_image_uses_root_asset_route_instead_of_preview_base_path(): void
    {
        $html = view('public-website.blog.show', [
            'context' => new PublicSiteContext('http', 'localhost:8000', '/dashboard/website/preview', $this->uuid(11), $this->uuid(1)),
            'website' => $this->website('SYIFA_ESSENTIAL'),
            'post' => $this->article(),
            'isPreview' => true,
        ])->render();

        self::assertStringContainsString('src="/assets/'.$this->uuid(71).'"', $html);
        self::assertStringContainsString('content="http://localhost:8000/assets/'.$this->uuid(71).'"', $html);
        self::assertStringNotContainsString('/dashboard/website/preview/assets/', $html);
    }

    public function test_single_article_slider_uses_featured_layout_without_redundant_controls(): void
    {
        $html = $this->renderSlider([$this->sliderArticle('panduan-kesihatan')]);

        self::assertStringContainsString('blog-slider--single', $html);
        self::assertStringContainsString('data-blog-slider-count="1"', $html);
        self::assertStringContainsString('href="/preview/blog/panduan-kesihatan"', $html);
        self::assertStringNotContainsString('data-blog-slider-previous', $html);
        self::assertStringNotContainsString('data-blog-slider-next', $html);
        self::assertStringNotContainsString('data-blog-slider-status', $html);
        self::assertStringNotContainsString('Lihat semua artikel', $html);
    }

    public function test_multiple_article_slider_keeps_navigation_and_live_status(): void
    {
        $html = $this->renderSlider([
            $this->sliderArticle('panduan-kesihatan'),
            $this->sliderArticle('penjagaan-harian'),
        ]);

        self::assertStringNotContainsString('blog-slider--single', $html);
        self::assertStringContainsString('data-blog-slider-count="2"', $html);
        self::assertStringContainsString('data-blog-slider-previous', $html);
        self::assertStringContainsString('data-blog-slider-next', $html);
        self::assertStringContainsString('data-blog-slider-status', $html);
        self::assertStringNotContainsString('Lihat semua artikel', $html);
    }

    private function website(string $template): stdClass
    {
        return (object) ['clinic_name' => 'Klinik Aman', 'template_id' => $template];
    }

    private function article(): stdClass
    {
        return (object) [
            'slug' => 'panduan-kesihatan', 'title' => 'Panduan Kesihatan', 'excerpt' => 'Informasi kesihatan umum.',
            'body_html' => '<h2>Penjagaan harian</h2><p>Kandungan selamat.</p>', 'category' => 'Kesihatan',
            'author_name' => 'Dr. Aminah', 'published_at' => '2026-08-15T02:00:00Z', 'last_changed_at' => '2026-08-15T03:00:00Z',
            'meta_title' => 'Panduan Kesihatan | Klinik Aman', 'meta_description' => 'Panduan kesihatan umum daripada Klinik Aman.',
            'canonical_url' => null, 'robots_directive' => 'index,follow', 'open_graph_title' => 'Panduan Kesihatan',
            'open_graph_description' => 'Panduan kesihatan umum.', 'featured_image_asset_id' => $this->uuid(71),
            'featured_image_alt_text' => 'Pasukan klinik memberikan penerangan kesihatan',
        ];
    }

    /** @param list<stdClass> $articles */
    private function renderSlider(array $articles): string
    {
        return Blade::render(
            '<x-public.blog-slider :articles="$articles" article-url-prefix="/preview/blog" />',
            ['articles' => collect($articles)],
        );
    }

    private function sliderArticle(string $slug): stdClass
    {
        return (object) [
            'slug' => $slug,
            'title' => 'Panduan Kesihatan',
            'excerpt' => 'Informasi kesihatan umum untuk seisi keluarga.',
            'category' => 'Kesihatan keluarga',
            'published_at' => '2026-08-15T02:00:00Z',
            'featured_image_asset_id' => $this->uuid(71),
            'featured_image_alt_text' => 'Keluarga mengamalkan gaya hidup sihat',
        ];
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
