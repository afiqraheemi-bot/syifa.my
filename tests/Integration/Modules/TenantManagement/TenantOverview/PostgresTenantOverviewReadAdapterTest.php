<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\TenantManagement\TenantOverview;

use App\Modules\TenantManagement\Infrastructure\Persistence\Queries\PostgresTenantOverviewReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresTenantOverviewReadAdapterTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('TENANT_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires TENANT_POSTGRES_TEST_DSN.');
        }
        config()->set('database.connections.tenant_overview_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('tenant_overview_test');
        $this->connection = DB::connection('tenant_overview_test');
        foreach ([
            'tenants (id text, status text)',
            'subscriptions (id text, tenant_id text, status text)',
            'websites (id text, tenant_id text, clinic_name text)',
            'website_published_snapshots (publication_id text, website_id text)',
            'website_designer_assignments (id text, tenant_id text, platform_identity_id text, assignment_status text, assigned_at timestamptz)',
            'clinic_owner_authorities (id text, tenant_id text, name text, email text, authority_status text)',
            'platform_workforce_credentials (platform_identity_id text, name text)',
        ] as $definition) {
            $this->connection()->statement('CREATE TEMP TABLE '.$definition);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('tenant_overview_test');
        parent::tearDown();
    }

    public function test_it_filters_searches_and_pages_enriched_tenant_projections(): void
    {
        $connection = $this->connection();
        $connection->table('tenants')->insert([
            ['id' => 'tenant-1', 'status' => 'active'],
            ['id' => 'tenant-2', 'status' => 'suspended'],
            ['id' => 'tenant-3', 'status' => 'active'],
        ]);
        $connection->table('subscriptions')->insert([['id' => 'sub-1', 'tenant_id' => 'tenant-1', 'status' => 'active']]);
        $connection->table('websites')->insert([['id' => 'website-1', 'tenant_id' => 'tenant-1', 'clinic_name' => 'Klinik Aisyah']]);
        $connection->table('website_published_snapshots')->insert([['publication_id' => 'pub-1', 'website_id' => 'website-1']]);
        $connection->table('clinic_owner_authorities')->insert([
            'id' => 'owner-1', 'tenant_id' => 'tenant-1', 'name' => 'Aisyah Rahman',
            'email' => 'aisyah@example.test', 'authority_status' => 'active',
        ]);
        $connection->table('platform_workforce_credentials')->insert([
            'platform_identity_id' => 'designer-1', 'name' => 'Designer One',
        ]);
        $connection->table('website_designer_assignments')->insert([
            'id' => 'assignment-1', 'tenant_id' => 'tenant-1', 'platform_identity_id' => 'designer-1',
            'assignment_status' => 'active', 'assigned_at' => '2026-08-25 10:00:00+08',
        ]);
        $adapter = new PostgresTenantOverviewReadAdapter($connection);

        $rows = $adapter->list('active', null, 10, 'aisyah');
        self::assertCount(1, $rows);
        self::assertSame('Klinik Aisyah', $rows[0]->clinicName);
        self::assertSame('Aisyah Rahman', $rows[0]->ownerName);
        self::assertSame('aisyah@example.test', $rows[0]->ownerEmail);
        self::assertSame('active', $rows[0]->subscriptionStatus);
        self::assertTrue($rows[0]->websitePublished);
        self::assertSame('Designer One', $rows[0]->websiteDesignerName);

        $next = $adapter->list(null, 'tenant-1', 10, null);
        self::assertSame(['tenant-2', 'tenant-3'], array_column($next, 'tenantId'));
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
