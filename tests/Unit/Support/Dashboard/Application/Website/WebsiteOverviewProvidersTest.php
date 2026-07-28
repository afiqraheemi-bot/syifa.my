<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSnapshotData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteDetailData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSummaryData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\DomainStatusProvider;
use App\Support\Dashboard\Application\Website\PublishStatusProvider;
use App\Support\Dashboard\Application\Website\SeoStatusProvider;
use App\Support\Dashboard\Application\Website\ThemeInformationProvider;
use App\Support\Dashboard\Application\Website\WebsiteQuickActionsProvider;
use App\Support\Dashboard\Application\Website\WebsiteStatusProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteOverviewProvidersTest extends TestCase
{
    public function test_website_publish_theme_and_seo_providers_project_real_query_data(): void
    {
        $websites = $this->websites(
            new WebsiteSummaryData('website-1', 'tenant-1', 'Klinik Aisyah', 'syifa-essential', 'published'),
            new WebsiteDetailData(
                'website-1', 'tenant-1', 'syifa-essential', 'published', 'Klinik Aisyah',
                'Rawatan keluarga yang dipercayai', '#112233', '#445566', null, null,
                'hello@aisyah.test', '+60123456789', 'Kuala Lumpur', [],
            ),
        );
        $snapshot = $this->snapshots(new PublishedWebsiteSnapshotData('publication-1', 'website-1', 4, new DateTimeImmutable('2026-08-20T10:00:00Z'), 'syifa-essential', 'Klinik Aisyah', 'Klinik Aisyah', 'hash'));
        $seo = $this->seo(new WebsiteSeoSummaryData('Klinik Aisyah', 'index,follow', true));

        $websiteStatus = (new WebsiteStatusProvider($websites))->provide($this->context())->data;
        self::assertSame('Published', $websiteStatus['value']);
        self::assertSame(
            'Rawatan keluarga yang dipercayai · hello@aisyah.test · +60123456789 · Kuala Lumpur',
            $websiteStatus['detail'],
        );
        self::assertSame('Published', (new PublishStatusProvider($websites, $snapshot))->provide($this->context())->data['value']);
        self::assertSame('syifa-essential', (new ThemeInformationProvider($websites))->provide($this->context())->data['value']);
        self::assertSame('Indexing enabled', (new SeoStatusProvider($websites, $seo))->provide($this->context())->data['value']);
    }

    public function test_missing_queries_and_unavailable_modules_return_explicit_placeholders(): void
    {
        $websites = $this->websites(null);

        self::assertSame('Not available', (new WebsiteStatusProvider($websites))->provide($this->context())->data['value']);
        self::assertSame('Not published', (new PublishStatusProvider($websites, $this->snapshots(null)))->provide($this->context())->data['value']);
        self::assertSame(
            'Preparing',
            (new DomainStatusProvider($this->addresses(null)))->provide($this->context())->data['value'],
        );
        self::assertSame('Not available', (new ThemeInformationProvider($websites))->provide($this->context())->data['value']);
        self::assertSame('Not available', (new SeoStatusProvider($websites, $this->seo(null)))->provide($this->context())->data['value']);
    }

    public function test_website_edit_action_targets_the_governed_content_editor(): void
    {
        $actions = (new WebsiteQuickActionsProvider('/dashboard/website/content'))->provide($this->context())->data;

        self::assertCount(1, $actions);
        self::assertSame([true], array_column($actions, 'available'));
        self::assertSame(['/dashboard/website/content'], array_column($actions, 'href'));
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner', 'owner-1', 'tenant-1', 'clinic_owner', 'Aisyah', 'shared.authenticated-route', []);
    }

    private function websites(?WebsiteSummaryData $summary, ?WebsiteDetailData $detail = null): WebsiteReadInterface
    {
        return new class($summary, $detail) implements WebsiteReadInterface
        {
            public function __construct(
                private readonly ?WebsiteSummaryData $summary,
                private readonly ?WebsiteDetailData $detail,
            ) {}

            public function summary(string $trustedTenantId): ?WebsiteSummaryData
            {
                return $this->summary;
            }

            public function detail(string $trustedTenantId): ?WebsiteDetailData
            {
                return $this->detail;
            }
        };
    }

    private function snapshots(?PublishedWebsiteSnapshotData $snapshot): WebsitePublishedSnapshotReadInterface
    {
        return new class($snapshot) implements WebsitePublishedSnapshotReadInterface
        {
            public function __construct(private readonly ?PublishedWebsiteSnapshotData $snapshot) {}

            public function latest(string $websiteId): ?PublishedWebsiteSnapshotData
            {
                return $this->snapshot;
            }
        };
    }

    private function addresses(?WebsitePublicAddressData $address): WebsitePublicAddressReadInterface
    {
        return new class($address) implements WebsitePublicAddressReadInterface
        {
            public function __construct(private readonly ?WebsitePublicAddressData $address) {}

            public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData
            {
                return $this->address;
            }

            public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
            {
                return $this->address;
            }

            public function resolveActiveHost(string $host): ?WebsitePublicAddressData
            {
                return $this->address?->active === true ? $this->address : null;
            }
        };
    }

    private function seo(?WebsiteSeoSummaryData $seo): WebsiteSeoSummaryReadInterface
    {
        return new class($seo) implements WebsiteSeoSummaryReadInterface
        {
            public function __construct(private readonly ?WebsiteSeoSummaryData $seo) {}

            public function summary(string $websiteId): ?WebsiteSeoSummaryData
            {
                return $this->seo;
            }
        };
    }
}
