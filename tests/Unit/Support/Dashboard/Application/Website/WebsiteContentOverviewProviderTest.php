<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\Website;

use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSectionSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSnapshotData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteDetailData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSummaryData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\Content\WebsiteContentOverviewProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteContentOverviewProviderTest extends TestCase
{
    public function test_published_section_evidence_projects_completion_and_health(): void
    {
        $snapshot = new PublishedWebsiteSnapshotData(
            'publication-1',
            'website-1',
            3,
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
            'SYIFA_ESSENTIAL',
            'Klinik Aisyah',
            'Klinik Aisyah',
            str_repeat('a', 64),
            [
                new PublishedWebsiteSectionSummaryData('HERO', 1, true, true, 1, ['Trusted healthcare']),
                new PublishedWebsiteSectionSummaryData('ABOUT', 2, true, false, 0, ['About us']),
                new PublishedWebsiteSectionSummaryData('SERVICES', 3, true, true, 4, ['Primary care', 'Vaccination']),
                new PublishedWebsiteSectionSummaryData('DOCTORS', 4, false, true, 2, ['Dr Aisyah', 'Dr Kumar']),
                new PublishedWebsiteSectionSummaryData('TESTIMONIALS', 5, true, true, 3, ['Patient A']),
                new PublishedWebsiteSectionSummaryData('GALLERY', 6, true, true, 5, ['Reception']),
                new PublishedWebsiteSectionSummaryData('FAQ', 7, true, true, 6, ['When are you open?']),
                new PublishedWebsiteSectionSummaryData('CONTACT', 8, true, true, 1, ['Kuala Lumpur', '+6012']),
            ],
        );

        $projection = (new WebsiteContentOverviewProvider(
            $this->websites(new WebsiteSummaryData('website-1', 'tenant-1', 'Klinik Aisyah', 'SYIFA_ESSENTIAL', 'published')),
            $this->snapshots($snapshot),
        ))->provide($this->context())->data;

        self::assertSame('6 of 8 complete', $projection['health']['value']);
        self::assertSame('Complete', $projection['sections'][0]['status']);
        self::assertSame('Needs attention', $projection['sections'][1]['status']);
        self::assertSame('Trusted healthcare', $projection['sections'][0]['detail']);
        self::assertSame('Primary care · Vaccination', $projection['sections'][2]['detail']);
        self::assertSame('Needs attention', $projection['sections'][3]['status']);
        self::assertSame('Kuala Lumpur · +6012', $projection['sections'][7]['detail']);
        self::assertSame(
            ['Homepage', 'About', 'Services', 'Doctors', 'Gallery', 'Testimonials', 'FAQ', 'Contact'],
            array_column($projection['sections'], 'title'),
        );
    }

    public function test_missing_website_or_snapshot_returns_explicit_placeholders(): void
    {
        $projection = (new WebsiteContentOverviewProvider(
            $this->websites(null),
            $this->snapshots(null),
        ))->provide($this->context())->data;

        self::assertSame('Not available', $projection['health']['value']);
        self::assertCount(8, $projection['sections']);
        self::assertSame(array_fill(0, 8, 'Not available'), array_column($projection['sections'], 'status'));
        self::assertSame(array_fill(0, 8, false), array_column($projection['sections'], 'complete'));
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner', 'owner-1', 'tenant-1', 'clinic_owner', 'Aisyah', 'shared.authenticated-route', []);
    }

    private function websites(?WebsiteSummaryData $summary): WebsiteReadInterface
    {
        return new class($summary) implements WebsiteReadInterface
        {
            public function __construct(private readonly ?WebsiteSummaryData $summary) {}

            public function summary(string $trustedTenantId): ?WebsiteSummaryData
            {
                return $this->summary;
            }

            public function detail(string $trustedTenantId): ?WebsiteDetailData
            {
                return null;
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
}
