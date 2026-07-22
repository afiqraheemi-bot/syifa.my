<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\ContactActionFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\InvalidPublicDeliveryValueException;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAssetPurpose;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoutePolicy;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
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
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\OriginPublicAssetUrlResolver;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\WebsitePublicationContentFactory;

final class PublicWebsiteDeliveryContractTest extends TestCase
{
    public function test_context_and_governed_routes_generate_deterministic_urls(): void
    {
        $context = new PublicSiteContext('https', 'clinic.example', '/care', $this->uuid(1));
        $routes = (new PublicRoutePolicy)->available($this->renderModel(), $context);

        self::assertSame('https://clinic.example/care/', $routes[PublicRoute::Home->value]->value);
        self::assertSame('https://clinic.example/care/#services', $routes[PublicRoute::Services->value]->value);
        self::assertSame('https://clinic.example/care/#booking', $routes[PublicRoute::Booking->value]->value);
        self::assertSame('https://clinic.example/care/privacy', $routes[PublicRoute::Privacy->value]->value);
        self::assertArrayNotHasKey('unknown', $routes);
    }

    #[DataProvider('invalidContexts')]
    public function test_context_rejects_host_scheme_and_path_attacks(string $scheme, string $host, string $path): void
    {
        $this->expectException(InvalidPublicDeliveryValueException::class);
        new PublicSiteContext($scheme, $host, $path);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidContexts(): iterable
    {
        yield 'scheme' => ['javascript', 'clinic.example', ''];
        yield 'host injection' => ['https', 'clinic.example@evil.test', ''];
        yield 'production http' => ['http', 'clinic.example', ''];
        yield 'traversal' => ['https', 'clinic.example', '/../private'];
    }

    public function test_asset_contact_seo_and_sitemap_delivery_use_only_render_values(): void
    {
        $model = $this->renderModel();
        $context = new PublicSiteContext('https', 'clinic.example', websiteId: $this->uuid(1));
        $resolver = new OriginPublicAssetUrlResolver('https://cdn.example');
        $document = (new PublicWebsiteDocumentFactory($resolver))->make($model, $context);

        self::assertSame('https://cdn.example/assets/'.$this->uuid(9990).'?purpose=content', $document->assetUrls[$this->uuid(9990)]->value);
        self::assertSame('tel:%2B6012', $document->contactActions->telephone);
        self::assertSame('mailto:hello%40clinic.test', $document->contactActions->email);
        self::assertSame('https://wa.me/60123456789', $document->contactActions->whatsApp?->value);
        self::assertStringContainsString('3.139%2C101.6869', $document->contactActions->directions?->value ?? '');
        self::assertSame('https://clinic.example/', $document->head->canonicalUrl->value);
        self::assertSame(['https://clinic.example/'], array_map(static fn ($url): string => $url->value, $document->sitemapUrls));
        self::assertStringContainsString('MedicalClinic', $document->head->jsonLd());
        self::assertStringNotContainsString('<script', $document->head->jsonLd());
        self::assertSame('https://cdn.example/assets/'.$this->uuid(9990).'?purpose=logo', $resolver->resolve($this->uuid(9990), PublicAssetPurpose::Logo)->value);
    }

    public function test_context_cannot_cross_publication_website_identity(): void
    {
        $this->expectException(InvalidPublicDeliveryValueException::class);
        (new PublicWebsiteDocumentFactory(new OriginPublicAssetUrlResolver('https://cdn.example')))->make($this->renderModel(), new PublicSiteContext('https', 'clinic.example', websiteId: $this->uuid(2)));
    }

    public function test_contact_actions_omit_missing_values(): void
    {
        $empty = new FooterRenderModel('Klinik Syifa', null, null, null, [], [], null, null, null);
        $actions = (new ContactActionFactory)->make($empty);

        self::assertNull($actions->telephone);
        self::assertNull($actions->email);
        self::assertNull($actions->whatsApp);
        self::assertNull($actions->directions);
    }

    private function renderModel(): PublicWebsiteRenderModel
    {
        $website = Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, new WebsiteBranding('Klinik Syifa', 'Trusted care', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'), array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)), new DateTimeImmutable('2026-08-19T00:00:00Z'));
        $website->readyForReview(new DateTimeImmutable('2026-08-19T01:00:00Z'));
        $website->publish(new WebsitePublicationEvidence(true, true), new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), new DateTimeImmutable('2026-08-19T02:00:00Z'));

        return (new PublicWebsiteRenderProjector)->project($website->publishedSnapshot());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
