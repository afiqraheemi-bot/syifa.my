<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Queries;

use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantNotFoundException;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteTenantResolver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Self-contained per Integration test convention already established by
 * PostgresWebsiteRepositoryTest: each test file owns its own ephemeral schema
 * on the shared disposable database and tears it down completely, so test
 * order/other Integration test files never affect this one (or vice versa).
 */
final class PostgresWebsiteTenantResolverTest extends TestCase
{
    private const string CONNECTION = 'website_tenant_resolver_integration';

    private ?ConnectionInterface $connection = null;

    private ?Migration $websitesMigration = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('WEBSITE_POSTGRES_TEST_DSN') ?: getenv('CLINIC_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires a dedicated disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, ['driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer', 'timezone' => 'UTC']);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        Schema::connection(self::CONNECTION)->dropIfExists('websites');
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        Schema::connection(self::CONNECTION)->create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        $migration = require base_path('database/migrations/website_builder/2026_08_07_000001_create_websites_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $this->websitesMigration = $migration;
    }

    protected function tearDown(): void
    {
        $this->websitesMigration?->down();
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_it_resolves_a_real_website_row_to_its_tenant(): void
    {
        $tenantId = (string) Str::uuid();
        $websiteId = (string) Str::uuid();
        $now = now();

        $this->connection->table('tenants')->insert(['id' => $tenantId]);
        $this->connection->table('websites')->insert([
            'id' => $websiteId, 'tenant_id' => $tenantId, 'template_id' => 'SYIFA_ESSENTIAL', 'lifecycle' => 'published',
            'clinic_name' => 'Klinik Test', 'primary_color' => '#176B50', 'secondary_color' => '#E8F0EA',
            'contact_email' => 'clinic@example.com', 'contact_phone' => '+60123456789', 'address' => '1 Test Street',
            'domain_created_at' => $now, 'domain_updated_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $resolved = (new PostgresWebsiteTenantResolver($this->connection))->forTrustedWebsite($websiteId);

        self::assertSame($tenantId, $resolved);
    }

    public function test_it_fails_closed_for_an_unknown_website(): void
    {
        $this->expectException(WebsiteTenantNotFoundException::class);

        (new PostgresWebsiteTenantResolver($this->connection))->forTrustedWebsite((string) Str::uuid());
    }
}
