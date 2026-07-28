<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\PlatformAdministration\Dashboard;

use App\Modules\PlatformAdministration\Infrastructure\Persistence\Dashboard\PostgresPlatformDashboardReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresPlatformDashboardReadAdapterTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN.');
        }
        config()->set('database.connections.platform_dashboard_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('platform_dashboard_test');
        $this->connection = DB::connection('platform_dashboard_test');
        $this->createTemporaryProjectionTables();
    }

    protected function tearDown(): void
    {
        DB::purge('platform_dashboard_test');
        parent::tearDown();
    }

    public function test_it_projects_platform_counts_health_and_recent_activity(): void
    {
        $connection = $this->connection();
        $connection->table('tenants')->insert([['id' => 'tenant-1'], ['id' => 'tenant-2']]);
        $connection->table('subscriptions')->insert([
            ['id' => 'subscription-1', 'status' => 'active'],
            ['id' => 'subscription-2', 'status' => 'expired'],
        ]);
        $connection->table('platform_workforce_credentials')->insert([
            ['platform_identity_id' => 'designer-1', 'role' => 'website_designer', 'account_status' => 'active'],
            ['platform_identity_id' => 'designer-2', 'role' => 'website_designer', 'account_status' => 'suspended'],
            ['platform_identity_id' => 'admin-1', 'role' => 'super_admin', 'account_status' => 'active'],
        ]);
        $connection->table('onboarding_jobs')->insert([
            ['id' => 'job-1', 'status' => 'in_progress'],
            ['id' => 'job-2', 'status' => 'completed'],
        ]);
        $connection->table('website_published_snapshots')->insert([
            ['publication_id' => 'publication-1', 'website_id' => 'website-1'],
            ['publication_id' => 'publication-2', 'website_id' => 'website-1'],
            ['publication_id' => 'publication-3', 'website_id' => 'website-2'],
        ]);
        $connection->table('bookings')->insert([['id' => 'booking-1'], ['id' => 'booking-2']]);
        $connection->table('audit_entries')->insert([
            ['audit_entry_id' => 'audit-1', 'action' => 'tenant.created', 'outcome' => 'success', 'occurred_at' => '2026-08-25 09:00:00+08'],
            ['audit_entry_id' => 'audit-2', 'action' => 'website.published', 'outcome' => 'success', 'occurred_at' => '2026-08-25 10:00:00+08'],
        ]);

        $data = (new PostgresPlatformDashboardReadAdapter($connection))->overview();

        self::assertSame(2, $data->tenants);
        self::assertSame(1, $data->activeSubscriptions);
        self::assertSame(1, $data->activeWebsiteDesigners);
        self::assertSame(1, $data->onboardingPipeline);
        self::assertSame(2, $data->publishedWebsites);
        self::assertSame(2, $data->bookings);
        self::assertTrue($data->platformHealthy);
        self::assertSame('audit-2', $data->recentActivity[0]->id);
    }

    private function createTemporaryProjectionTables(): void
    {
        foreach ([
            'tenants (id text)',
            'subscriptions (id text, status text)',
            'platform_workforce_credentials (platform_identity_id text, role text, account_status text)',
            'onboarding_jobs (id text, status text)',
            'website_published_snapshots (publication_id text, website_id text)',
            'bookings (id text)',
            'audit_entries (audit_entry_id text, action text, outcome text, occurred_at timestamptz)',
        ] as $definition) {
            $this->connection()->statement('CREATE TEMP TABLE '.$definition);
        }
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
