<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantNotFoundException;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteTenantResolver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PostgresWebsiteTenantResolverTest extends TestCase
{
    public function test_it_resolves_the_tenant_id_for_a_known_website(): void
    {
        $row = new stdClass;
        $row->tenant_id = 'tenant-123';

        $builder = $this->createMock(Builder::class);
        $builder->method('where')->with('id', 'website-1')->willReturnSelf();
        $builder->method('first')->with(['tenant_id'])->willReturn($row);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->with('websites')->willReturn($builder);

        $tenantId = (new PostgresWebsiteTenantResolver($connection))->forTrustedWebsite('website-1');

        self::assertSame('tenant-123', $tenantId);
    }

    public function test_it_fails_closed_when_the_website_is_unknown(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('first')->willReturn(null);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->willReturn($builder);

        $this->expectException(WebsiteTenantNotFoundException::class);

        (new PostgresWebsiteTenantResolver($connection))->forTrustedWebsite('unknown-website');
    }
}
