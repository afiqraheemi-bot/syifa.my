<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Queue\WebsiteDesignerQueueCriteria;
use App\Support\Dashboard\Application\WebsiteDesigner\Queue\WebsiteDesignerQueueProvider;
use DateTimeImmutable;
use Tests\TestCase;

final class WebsiteDesignerQueueProviderTest extends TestCase
{
    public function test_it_sanitizes_criteria_and_projects_a_cursor_page(): void
    {
        $read = new QueueRecordedRead;
        $provider = new WebsiteDesignerQueueProvider($read);
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
