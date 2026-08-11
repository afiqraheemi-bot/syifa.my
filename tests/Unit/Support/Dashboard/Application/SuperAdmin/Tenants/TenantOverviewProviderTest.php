<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\SuperAdmin\Tenants;

use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewSummaryData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Tenants\TenantOverviewCriteria;
use App\Support\Dashboard\Application\SuperAdmin\Tenants\TenantOverviewProvider;
use Tests\TestCase;

final class TenantOverviewProviderTest extends TestCase
{
    public function test_it_sanitizes_criteria_and_projects_cursor_pagination(): void
    {
        $read = new RecordedTenantOverviewRead;
        $context = new AuthorizationContext(
            'platform_identity', 'admin-1', null, 'super_admin', 'Sarah',
            'platform_identity', [],
        );

        $projection = (new TenantOverviewProvider($read))->provide(
            $context,
            TenantOverviewCriteria::fromInput([
                'search' => ' tenant ',
                'status' => 'active',
                'per_page' => 10,
            ]),
        );

        self::assertSame(['active', null, 11, 'tenant'], $read->criteria);
        self::assertCount(10, $projection->data['items']);
        self::assertTrue($projection->data['pagination']['hasMore']);
        self::assertStringContainsString('cursor=tenant-10', $projection->data['pagination']['nextHref']);
        self::assertSame('Active', $projection->data['items'][0]['subscriptionStatusLabel']);
        self::assertSame('Published', $projection->data['items'][0]['websitePublicationStatus']);
        self::assertSame('Clinic 1', $projection->data['items'][0]['clinicName']);
        self::assertSame('Owner 1', $projection->data['items'][0]['ownerName']);
        self::assertSame('owner1@example.test', $projection->data['items'][0]['ownerEmail']);
        self::assertSame('Designer 1', $projection->data['items'][0]['websiteDesigner']);
        self::assertSame('Clinic, owner, email, host or ID', $projection->data['search']['placeholder']);
        self::assertSame(11, $projection->data['summary'][0]['value']);
        self::assertSame('TENANT-1', $projection->data['items'][0]['reference']);
    }
}

final class RecordedTenantOverviewRead implements TenantOverviewReadInterface
{
    /** @var array{?string, ?string, int, ?string}|null */
    public ?array $criteria = null;

    public function summary(): TenantOverviewSummaryData
    {
        return new TenantOverviewSummaryData(11, 9, 1, 1);
    }

    public function list(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $this->criteria = [$status, $cursor, $limit, $search];

        return array_map(
            static fn (int $index): TenantOverviewData => new TenantOverviewData(
                'tenant-'.$index,
                'Clinic '.$index,
                'Owner '.$index,
                'owner'.$index.'@example.test',
                'active',
                'active',
                true,
                'Designer '.$index,
            ),
            range(1, 11),
        );
    }
}
