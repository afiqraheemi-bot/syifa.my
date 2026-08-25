<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteDetailData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSummaryData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Queue\WebsiteDesignerQueueCriteria;
use App\Support\Dashboard\Application\WebsiteDesigner\Queue\WebsiteDesignerQueueProvider;
use DateTimeImmutable;
use Tests\TestCase;

final class WebsiteDesignerQueueProviderTest extends TestCase
{
    public function test_it_accepts_operational_status_groups(): void
    {
        foreach (['website_setup', 'review_attention', 'needs_attention'] as $status) {
            self::assertSame(
                $status,
                WebsiteDesignerQueueCriteria::fromInput(['status' => $status])->status,
            );
        }

        self::assertNull(WebsiteDesignerQueueCriteria::fromInput(['status' => 'unknown'])->status);
    }

    public function test_it_sanitizes_criteria_and_projects_a_cursor_page(): void
    {
        $read = new QueueRecordedRead;
        $provider = new WebsiteDesignerQueueProvider(
            $read,
            new QueueWebsiteRead,
            new QueueAddressRead,
        );
        $context = new AuthorizationContext(
            'platform_identity', 'designer-1', null, 'website_designer', 'Alya',
            'platform_identity', [],
        );

        $projection = $provider->provide($context, WebsiteDesignerQueueCriteria::fromInput([
            'search' => ' job ',
            'status' => 'in_progress',
            'per_page' => 10,
        ]));

        self::assertSame(['designer-1', 'in_progress', null, 11, 'job'], $read->criteria);
        self::assertCount(10, $projection->data['items']);
        self::assertTrue($projection->data['pagination']['hasMore']);
        self::assertStringContainsString('cursor=assignment-10', $projection->data['pagination']['nextHref']);
        self::assertSame('Current', $projection->data['items'][0]['websiteSetup']);
        self::assertSame('Not current', $projection->data['items'][0]['review']);
        self::assertSame('Clinic 1', $projection->data['items'][0]['clinicName']);
        self::assertSame('clinic-1.syifa.my', $projection->data['items'][0]['publicHost']);
        self::assertSame('https://clinic-1.syifa.my', $projection->data['items'][0]['publicUrl']);
        self::assertSame('JOB-JOB-1', $projection->data['items'][0]['jobReference']);
        self::assertSame('TENANT-TENANT-1', $projection->data['items'][0]['tenantReference']);
        self::assertSame('WEB-WEBSITE-', $projection->data['items'][0]['websiteReference']);
    }
}

final class QueueWebsiteRead implements WebsiteReadInterface
{
    public function summary(string $trustedTenantId): WebsiteSummaryData
    {
        $suffix = str_replace('tenant-', '', $trustedTenantId);

        return new WebsiteSummaryData(
            'website-'.$suffix,
            $trustedTenantId,
            'Clinic '.$suffix,
            'syifa-essential',
            'draft',
        );
    }

    public function detail(string $trustedTenantId): ?WebsiteDetailData
    {
        return null;
    }
}

final class QueueAddressRead implements WebsitePublicAddressReadInterface
{
    public function forWebsite(string $trustedTenantId, string $websiteId): WebsitePublicAddressData
    {
        $suffix = str_replace('tenant-', '', $trustedTenantId);

        return new WebsitePublicAddressData(
            $websiteId,
            $trustedTenantId,
            'clinic-'.$suffix.'.syifa.my',
            'https://clinic-'.$suffix.'.syifa.my',
            true,
        );
    }

    public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
    {
        return null;
    }

    public function resolveActiveHost(string $host): ?WebsitePublicAddressData
    {
        return null;
    }
}

final class QueueRecordedRead implements WebsiteDesignerDashboardReadInterface
{
    /** @var array{string, ?string, ?string, int, ?string}|null */
    public ?array $criteria = null;

    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(0, 0, 0, 0, 0, 0, []);
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $this->criteria = [$platformIdentityId, $status, $cursor, $limit, $search];

        return array_map(
            static fn (int $index): WebsiteDesignerQueueJobData => new WebsiteDesignerQueueJobData(
                'assignment-'.$index,
                'job-'.$index,
                'tenant-'.$index,
                'website-'.$index,
                'in_progress',
                new DateTimeImmutable("2026-08-{$index}T09:00:00+08:00"),
                new DateTimeImmutable("2026-08-{$index}T10:00:00+08:00"),
            ),
            range(1, 11),
        );
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        return null;
    }
}
