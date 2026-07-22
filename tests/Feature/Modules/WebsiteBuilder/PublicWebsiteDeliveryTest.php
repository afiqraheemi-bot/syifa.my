<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
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
            ->assertSee('https://clinic.example/#booking', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('storage_key');
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

    private function renderModel(string $clinicName): PublicWebsiteRenderModel
    {
        $website = Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, new WebsiteBranding($clinicName, 'Trusted care', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'), array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)), new DateTimeImmutable('2026-08-20T00:00:00Z'));
        $website->readyForReview(new DateTimeImmutable('2026-08-20T01:00:00Z'));
        $website->publish(new WebsitePublicationEvidence(true, true), new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), new DateTimeImmutable('2026-08-20T02:00:00Z'));

        return (new PublicWebsiteRenderProjector)->project($website->publishedSnapshot());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
