<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
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
            ->assertSee('loading="lazy"', false)
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
        self::assertLessThan(strpos($html, 'id="booking"'), strpos($html, 'id="contact"'));
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
