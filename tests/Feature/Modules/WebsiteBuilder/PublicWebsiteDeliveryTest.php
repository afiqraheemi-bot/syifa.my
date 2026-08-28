<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeaderRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\SeoRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServiceItemRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WebsitePublicationContentFactory;
use Tests\TestCase;

final class PublicWebsiteDeliveryTest extends TestCase
{
    public function test_unknown_host_and_unavailable_publication_return_safe_404(): void
    {
        $this->get('https://unknown.example/')->assertNotFound()->assertDontSee($this->uuid(1));

        config()->set('public_website_delivery.sites', ['clinic.example' => ['website_id' => $this->uuid(1)]]);
        $this->app->forgetInstance(PublicSiteContextFactoryInterface::class);
        $this->get('https://clinic.example/')->assertNotFound()->assertDontSee($this->uuid(1));
    }

    public function test_thin_document_delivery_renders_safe_snapshot_values_and_governed_destinations(): void
    {
        config()->set('public_website_delivery.sites', ['clinic.example' => ['website_id' => $this->uuid(1)]]);
        $this->app->forgetInstance(PublicSiteContextFactoryInterface::class);
        $model = $this->renderModel('Klinik Syifa');
        $this->app->instance(PublicWebsiteRenderModelProviderInterface::class, new readonly class($model) implements PublicWebsiteRenderModelProviderInterface
        {
            public function __construct(private PublicWebsiteRenderModel $model) {}

            public function find(PublicSiteContext $context): ?PublicWebsiteRenderModel
            {
                return $this->model;
            }
        });

        $response = $this->get('https://clinic.example/');

        $response->assertOk()
            ->assertSee('Klinik Syifa')
            ->assertSee('https://clinic.example/booking', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('storage_key');
    }

    public function test_published_website_exposes_host_specific_crawler_discovery_files(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $this->get('https://clinic.example/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertDontSee('Disallow: /booking', false)
            ->assertSee('Sitemap: https://clinic.example/sitemap.xml', false);

        $this->get('https://clinic.example/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://clinic.example/</loc>', false)
            ->assertSee('<loc>https://clinic.example/booking</loc>', false);
    }

    public function test_complete_reference_document_preserves_sections_semantics_and_truthful_booking(): void
    {
        $this->bindWebsite($this->renderModel('A Very Long Published Clinic Identity That Must Wrap Safely'));

        $html = $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('Skip to main content')
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('id="about"', false)
            ->assertSee('id="services"', false)
            ->assertSee('Rawatan Kesihatan Am')
            ->assertSee('Dr Syifa')
            ->assertSee('Excellent care.')
            ->assertSee('alt="Ruang menunggu klinik yang selesa"', false)
            ->assertSee('width="800"', false)
            ->assertSee('height="600"', false)
            ->assertSee('loading="eager"', false)
            ->assertSee('fetchpriority="high"', false)
            ->assertSee('id="contact"', false)
            ->assertSee('Business hours')
            ->assertSee('id="booking"', false)
            ->assertDontSee('<form', false)
            ->assertDontSee('<input', false)
            ->assertDontSee('Coming Soon')
            ->assertDontSee('No Data')
            ->assertDontSee('★★★★★')
            ->getContent();

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertLessThan(strpos($html, 'id="services"'), strpos($html, 'id="about"'));
        self::assertLessThan(strpos($html, 'id="gallery"'), strpos($html, 'id="doctors"'));
        self::assertLessThan(strpos($html, 'id="contact"'), strpos($html, 'id="booking"'));
    }

    public function test_optional_sections_and_images_are_omitted_without_empty_wrappers(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $sections = array_values(array_filter($model->sections, static fn ($section): bool => ! in_array($section->type(), ['DOCTORS', 'GALLERY', 'TESTIMONIALS'], true)));
        $model = new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $model->footer, $sections, $model->assets, $model->publication);
        $this->bindWebsite($model);

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertDontSee('id="doctors"', false)
            ->assertDontSee('id="gallery"', false)
            ->assertDontSee('id="testimonials"', false)
            ->assertDontSee('Meet our doctors')
            ->assertDontSee('placeholder', false)
            ->assertSee('id="services"', false)
            ->assertSee('id="booking"', false);
    }

    public function test_hero_image_is_prioritized_without_changing_published_content_order(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $sections = $model->sections;
        $hero = $sections[0];
        self::assertInstanceOf(HeroSectionRenderModel::class, $hero);
        $sections[0] = new HeroSectionRenderModel($hero->headline, $hero->subheadline, $hero->primaryCtaLabel, $hero->primaryCtaTarget, $hero->secondaryCtaLabel, $hero->secondaryCtaTarget, $this->uuid(9990));
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $model->footer, $sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('class="responsive-image hero__image"', false)
            ->assertSee('loading="eager"', false)
            ->assertSee('fetchpriority="high"', false);
    }

    public function test_hero_uses_configured_cta_targets_instead_of_silent_fixed_destinations(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $sections = $model->sections;
        $hero = $sections[0];
        self::assertInstanceOf(HeroSectionRenderModel::class, $hero);
        $sections[0] = new HeroSectionRenderModel(
            $hero->headline,
            $hero->subheadline,
            'External booking',
            'https://booking.example.test/start',
            'About our clinic',
            '/#about',
            $hero->heroImageAssetId,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $model->footer, $sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('href="https://booking.example.test/start"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('href="https://clinic.example/#about"', false);
    }

    public function test_featured_service_uses_subtle_textual_emphasis_without_reordering(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $sections = $model->sections;
        self::assertInstanceOf(ServicesSectionRenderModel::class, $sections[2]);
        $service = $sections[2]->services[0];
        $sections[2] = new ServicesSectionRenderModel([new ServiceItemRenderModel($service->serviceId, $service->displayName, $service->shortDescription, $service->displayOrder, true)]);
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $model->footer, $sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('service-card--featured', false)
            ->assertSee('class="badge"', false)
            ->assertSee('Featured');
    }

    public function test_safe_404_uses_accessible_reference_error_document(): void
    {
        $this->get('/missing-page')
            ->assertNotFound()
            ->assertSee('We couldn’t find that page.')
            ->assertSee('Return home')
            ->assertSee('content="noindex"', false)
            ->assertDontSee('WebsiteId')
            ->assertDontSee('PublicationId')
            ->assertDontSee('Stack trace');
    }

    public function test_legal_routes_fail_closed_until_platform_copy_is_approved(): void
    {
        $this->get('/privacy')->assertNotFound();
        $this->get('/terms')->assertNotFound();
    }

    public function test_platform_legal_copy_is_versioned_and_html_escaped(): void
    {
        config()->set('public_website_delivery.legal.privacy', [
            'version' => '2026-08-v1',
            'title' => 'Privacy Notice',
            'paragraphs' => ['Test policy <script>alert(1)</script>'],
        ]);
        $this->app->forgetInstance(PlatformLegalContentProviderInterface::class);

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('2026-08-v1')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_footer_includes_only_approved_available_legal_documents(): void
    {
        config()->set('public_website_delivery.legal.privacy', ['version' => 'v1', 'title' => 'Privacy', 'paragraphs' => ['Approved test policy.']]);
        config()->set('public_website_delivery.legal.terms', null);
        $this->app->forgetInstance(PlatformLegalContentProviderInterface::class);
        $this->app->forgetInstance(PublicWebsiteDocumentFactory::class);
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('href="https://clinic.example/privacy"', false)
            ->assertDontSee('href="https://clinic.example/terms"', false);
    }

    public function test_desktop_navigation_never_exceeds_six_primary_items_plus_booking_and_omits_home(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $html = $this->get('https://clinic.example/')->assertOk()->getContent();

        preg_match('/<nav id="public-navigation".*?<\/nav>/s', $html, $matches);
        self::assertNotSame([], $matches, 'Primary navigation region was not found.');
        preg_match_all('/<a href="[^"]*">([^<]+)<\/a>/', $matches[0], $linkMatches);
        $labels = $linkMatches[1];

        self::assertNotContains('Home', $labels);
        self::assertLessThanOrEqual(6, count($labels));
        self::assertDontSeeBookingDuplicatedInside($matches[0]);
    }

    public function test_brand_and_logo_remain_the_canonical_home_link(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<a class="brand" href="https://clinic.example/"', false);
    }

    public function test_booking_cta_remains_present_and_reachable_from_the_header(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('navbar__booking', false)
            ->assertSee('Book Appointment');
    }

    public function test_brand_tokens_are_derived_only_from_the_published_snapshot_branding_colours(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        // renderModel() publishes with WebsiteBranding primary #112233 / secondary #AABBCC.
        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('--brand-primary:#112233;--brand-primary-hover:#0E1C2A;--brand-primary-active:#0C1723;--brand-on-primary:#F9FCFA;--brand-secondary:#F5F7F9;--brand-on-secondary:#18221F;', false);
    }

    #[DataProvider('officialTemplates')]
    public function test_every_official_template_selects_its_governed_public_personality(
        string $templateId,
        string $selector,
    ): void {
        $this->bindWebsite($this->renderModel('Klinik Syifa', TemplateId::from($templateId)));

        $response = $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('data-template="'.$selector.'"', false)
            ->assertSee('Book Appointment')
            ->assertSee('id="services"', false)
            ->assertSee('id="contact"', false);

        if ($templateId === TemplateId::SyifaEssential->value) {
            $response->assertDontSee('hero__media--fallback', false);
        } else {
            $response->assertSee('hero__media--fallback', false);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function officialTemplates(): array
    {
        return [
            'Essential' => ['SYIFA_ESSENTIAL', 'syifa-essential'],
            'Care' => ['SYIFA_CARE', 'syifa-care'],
            'Dental' => ['SYIFA_DENTAL', 'syifa-dental'],
            'Aesthetic' => ['SYIFA_AESTHETIC', 'syifa-aesthetic'],
            'Specialist' => ['SYIFA_SPECIALIST', 'syifa-specialist'],
        ];
    }

    public function test_an_unsafe_tenant_brand_colour_falls_back_to_the_current_default_appearance(): void
    {
        $website = Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, new WebsiteBranding('Klinik Syifa', 'Trusted care', '#FF0000', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'), array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)), new DateTimeImmutable('2026-08-20T00:00:00Z'));
        $website->readyForReview(new DateTimeImmutable('2026-08-20T01:00:00Z'));
        $website->publish(new WebsitePublicationEvidence(true, true), new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), new DateTimeImmutable('2026-08-20T02:00:00Z'));
        $model = (new PublicWebsiteRenderProjector)->project($website->publishedSnapshot());
        $this->bindWebsite($model);

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('--brand-primary:#176B50;--brand-primary-hover:#10543F;--brand-primary-active:#0C4434;--brand-on-primary:#F9FCFA;', false);
    }

    public function test_a_configured_canonical_url_is_used_instead_of_the_current_request_url(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $seo = new SeoRenderModel(
            $model->seo->metaTitle,
            $model->seo->metaDescription,
            $model->seo->metaKeywords,
            'https://www.klinik-syifa.my/',
            $model->seo->robotsDirective,
            $model->seo->openGraphTitle,
            $model->seo->openGraphDescription,
            $model->seo->openGraphImageAssetId,
            $model->seo->indexingEnabled,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $seo, $model->header, $model->footer, $model->sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://www.klinik-syifa.my/">', false)
            ->assertSee('<meta property="og:url" content="https://www.klinik-syifa.my/">', false);
    }

    public function test_disabling_indexing_forces_noindex_regardless_of_the_configured_robots_directive(): void
    {
        // "Indexing enabled" is presented to the Clinic Owner as a single
        // promise — "Allow this website to be listed on Google" — so turning
        // it off must be authoritative even if the separate robots directive
        // field was left at "index,follow" (its default).
        $model = $this->renderModel('Klinik Syifa');
        $seo = new SeoRenderModel(
            $model->seo->metaTitle,
            $model->seo->metaDescription,
            $model->seo->metaKeywords,
            $model->seo->canonicalUrl,
            'index,follow',
            $model->seo->openGraphTitle,
            $model->seo->openGraphDescription,
            $model->seo->openGraphImageAssetId,
            false,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $seo, $model->header, $model->footer, $model->sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $this->get('https://clinic.example/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertDontSee('Sitemap:', false);
    }

    /**
     * Regression: there is no dashboard field to set Open Graph title and
     * description independently, so a stale value stored from before the
     * meta title/description were last edited must never be shown - the
     * page always reads the current meta title/description instead.
     */
    public function test_open_graph_title_and_description_always_use_the_current_meta_title_and_description(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $seo = new SeoRenderModel(
            'Klinik Syifa - Rawatan Keluarga',
            'Rawatan klinik am dan pemeriksaan kesihatan keluarga.',
            $model->seo->metaKeywords,
            $model->seo->canonicalUrl,
            $model->seo->robotsDirective,
            'klinik syifa',
            'klinik syifa',
            $model->seo->openGraphImageAssetId,
            $model->seo->indexingEnabled,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $seo, $model->header, $model->footer, $model->sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Klinik Syifa - Rawatan Keluarga">', false)
            ->assertSee('<meta property="og:description" content="Rawatan klinik am dan pemeriksaan kesihatan keluarga.">', false)
            ->assertSee('<meta name="twitter:title" content="Klinik Syifa - Rawatan Keluarga">', false)
            ->assertDontSee('content="klinik syifa"', false);
    }

    /**
     * Regression: a clinic with no explicit Open Graph image, no hero photo,
     * and no logo must not emit a broken/empty og:image tag - link previews
     * should fall back to a plain summary card rather than a dead image.
     */
    public function test_a_website_without_any_available_image_omits_the_open_graph_image_tag(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertDontSee('property="og:image"', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    /**
     * Regression: og:image has no dedicated dashboard field, so the hero
     * photo - the clinic's own best visual - is used as the share-card image
     * instead of leaving link previews on WhatsApp/Facebook without a thumbnail.
     */
    public function test_a_website_with_a_hero_photo_uses_it_as_the_open_graph_image(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $sections = $model->sections;
        $hero = $sections[0];
        self::assertInstanceOf(HeroSectionRenderModel::class, $hero);
        $sections[0] = new HeroSectionRenderModel($hero->headline, $hero->subheadline, $hero->primaryCtaLabel, $hero->primaryCtaTarget, $hero->secondaryCtaLabel, $hero->secondaryCtaTarget, $this->uuid(9990));
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $model->footer, $sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    /**
     * Regression: with no hero photo either, the clinic's logo is still a
     * better share-card image than none at all.
     */
    public function test_a_website_without_a_hero_photo_falls_back_to_its_logo_for_the_open_graph_image(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $header = new HeaderRenderModel($model->header->clinicName, $model->header->tagline, $this->uuid(9990), $model->header->logoDisplaySize);
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $header, $model->footer, $model->sections, $model->assets, $model->publication));

        $this->get('https://clinic.example/')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    /**
     * The default test fixture's contact details ("hello@clinic.test") are
     * exactly the kind of placeholder/demo data a clinic leaves behind
     * before publishing real content - structured data must never assert
     * fake contact details to search engines.
     */
    public function test_structured_data_omits_contact_details_for_placeholder_demo_data(): void
    {
        $this->bindWebsite($this->renderModel('Klinik Syifa'));

        $jsonLd = $this->get('https://clinic.example/')->assertOk()->getContent();

        self::assertStringNotContainsString('"telephone"', $jsonLd);
        self::assertStringNotContainsString('"PostalAddress"', $jsonLd);
    }

    /**
     * Once real contact details and a full address are configured, and the
     * meta title is still the unedited default, the public page gets richer
     * localised structured data and search-result copy - a real, parseable
     * city/region and services list unlock this; a clinic that hasn't set
     * either yet keeps the plain fallback.
     */
    public function test_structured_data_and_search_copy_are_enriched_once_real_contact_details_and_location_are_configured(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $footer = new FooterRenderModel(
            $model->footer->clinicName,
            'hello@kliniksyifa.my',
            '+60123456789',
            'No 88, Jalan Kesihatan, 08000 Sungai Petani, Kedah, Malaysia',
            $model->footer->socialLinks,
            $model->footer->businessHours,
            $model->footer->whatsAppNumber,
            $model->footer->latitude,
            $model->footer->longitude,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $model->seo, $model->header, $footer, $model->sections, $model->assets, $model->publication));

        $html = $this->get('https://clinic.example/')->assertOk()->getContent();

        self::assertStringContainsString('"telephone":"+60123456789"', $html);
        self::assertStringContainsString('"addressLocality":"Sungai Petani"', $html);
        self::assertStringContainsString('"addressRegion":"Kedah"', $html);
        self::assertStringContainsString('"postalCode":"08000"', $html);
        self::assertStringContainsString('"medicalSpecialty":"PrimaryCare"', $html);
        self::assertStringContainsString('"@type":"ReserveAction"', $html);
        self::assertStringContainsString('<title>Klinik Syifa | Klinik di Sungai Petani</title>', $html);
    }

    /**
     * Regression: an owner-written meta title is visible and editable in the
     * SEO dashboard - it must never be silently replaced by auto-generated
     * copy, even once the clinic has a full address and services configured,
     * or the public page would show something different from what the
     * dashboard displays back to the owner.
     */
    public function test_a_customized_meta_title_is_never_replaced_by_auto_generated_seo_copy(): void
    {
        $model = $this->renderModel('Klinik Syifa');
        $footer = new FooterRenderModel(
            $model->footer->clinicName,
            'hello@kliniksyifa.my',
            '+60123456789',
            'No 88, Jalan Kesihatan, 08000 Sungai Petani, Kedah, Malaysia',
            $model->footer->socialLinks,
            $model->footer->businessHours,
            $model->footer->whatsAppNumber,
            $model->footer->latitude,
            $model->footer->longitude,
        );
        $seo = new SeoRenderModel(
            'Klinik Syifa - Trusted Family Clinic in Kedah',
            'Our own carefully written description of the clinic.',
            $model->seo->metaKeywords,
            $model->seo->canonicalUrl,
            $model->seo->robotsDirective,
            $model->seo->openGraphTitle,
            $model->seo->openGraphDescription,
            $model->seo->openGraphImageAssetId,
            $model->seo->indexingEnabled,
        );
        $this->bindWebsite(new PublicWebsiteRenderModel($model->website, $model->branding, $seo, $model->header, $footer, $model->sections, $model->assets, $model->publication));

        $html = $this->get('https://clinic.example/')->assertOk()->getContent();

        self::assertStringContainsString('<title>Klinik Syifa - Trusted Family Clinic in Kedah</title>', $html);
        self::assertStringContainsString('Our own carefully written description of the clinic.', $html);
        self::assertStringNotContainsString('Klinik di Sungai Petani', $html);
    }

    private static function assertDontSeeBookingDuplicatedInside(string $navigationHtml): void
    {
        self::assertSame(0, substr_count($navigationHtml, 'Book Appointment'));
    }

    private function renderModel(
        string $clinicName,
        TemplateId $template = TemplateId::SyifaEssential,
    ): PublicWebsiteRenderModel {
        $website = Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), $template, new WebsiteBranding($clinicName, 'Trusted care', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'), array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)), new DateTimeImmutable('2026-08-20T00:00:00Z'));
        $website->readyForReview(new DateTimeImmutable('2026-08-20T01:00:00Z'));
        $website->publish(new WebsitePublicationEvidence(true, true), new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), new DateTimeImmutable('2026-08-20T02:00:00Z'));

        return (new PublicWebsiteRenderProjector)->project($website->publishedSnapshot());
    }

    private function bindWebsite(PublicWebsiteRenderModel $model): void
    {
        config()->set('public_website_delivery.sites', ['clinic.example' => ['website_id' => $this->uuid(1)]]);
        $this->app->forgetInstance(PublicSiteContextFactoryInterface::class);
        $this->app->instance(PublicWebsiteRenderModelProviderInterface::class, new readonly class($model) implements PublicWebsiteRenderModelProviderInterface
        {
            public function __construct(private PublicWebsiteRenderModel $model) {}

            public function find(PublicSiteContext $context): ?PublicWebsiteRenderModel
            {
                return $this->model;
            }
        });
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
