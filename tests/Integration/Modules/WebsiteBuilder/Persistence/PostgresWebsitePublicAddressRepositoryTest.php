<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicSiteContextFactory;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsitePublicAddressRepository;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresWebsitePublicAddressRepositoryTest extends TestCase
{
    private const string CONNECTION = 'website_address_postgres_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Closure(): void> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('WEBSITE_POSTGRES_TEST_DSN')
            ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires a dedicated disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropAllTables();
        Schema::connection(self::CONNECTION)->create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
        $this->db()->table('tenants')->insert([
            ['id' => $this->uuid(1)],
            ['id' => $this->uuid(2)],
        ]);
        foreach ([
            '2026_08_07_000001_create_websites_table.php',
            '2026_08_11_000001_create_website_assets_table.php',
            '2026_08_27_000001_create_website_public_hosts.php',
            '2026_08_27_000002_add_website_public_host_ownership_constraint.php',
        ] as $file) {
            $migration = require base_path('database/migrations/website_builder/'.$file);
            self::assertIsObject($migration);
            self::assertTrue(method_exists($migration, 'up'));
            self::assertTrue(method_exists($migration, 'down'));
            $migration->up();
            $this->migrations[] = Closure::fromCallable([$migration, 'down']);
        }
        $this->insertWebsite($this->uuid(10), $this->uuid(1), 'draft');
        $this->insertWebsite($this->uuid(20), $this->uuid(2), 'draft');
    }

    protected function tearDown(): void
    {
        if ($this->connection === null) {
            parent::tearDown();

            return;
        }

        foreach (array_reverse($this->migrations) as $down) {
            $down();
        }
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_reservation_is_unique_tenant_safe_and_bidirectionally_resolved_only_when_active(): void
    {
        $addresses = new PostgresWebsitePublicAddressRepository($this->db());
        $reserved = $addresses->reservePrimary(
            $this->uuid(100),
            $this->uuid(1),
            $this->uuid(10),
            'Klinik-One.SYIFA.MY.',
            new DateTimeImmutable('2026-08-27T00:00:00Z'),
        );

        self::assertSame('klinik-one.syifa.my', $reserved->host);
        self::assertFalse($reserved->active);
        self::assertSame('preparing', $reserved->status());
        self::assertFalse($addresses->isAvailable('klinik-one.syifa.my', $this->uuid(20)));
        self::assertNull($addresses->resolveActiveHost('klinik-one.syifa.my'));

        try {
            $addresses->reservePrimary(
                $this->uuid(101),
                $this->uuid(2),
                $this->uuid(20),
                'klinik-one.syifa.my',
                new DateTimeImmutable('2026-08-27T00:01:00Z'),
            );
            self::fail('Expected a cross-tenant hostname takeover to be rejected.');
        } catch (InvalidWebsiteValueException $exception) {
            self::assertSame(
                'The requested Website subdomain is not available.',
                $exception->getMessage(),
            );
        }

        $addresses->activatePrimary(
            $this->uuid(1),
            $this->uuid(10),
            new DateTimeImmutable('2026-08-27T00:02:00Z'),
        );
        self::assertNull($addresses->resolveActiveHost('klinik-one.syifa.my'));
        $this->db()->table('websites')->where('id', $this->uuid(10))->update([
            'lifecycle' => 'published',
        ]);

        $resolved = $addresses->resolveActiveHost('KLINIK-ONE.SYIFA.MY.');
        self::assertNotNull($resolved);
        self::assertSame($this->uuid(10), $resolved->websiteId);
        self::assertSame($this->uuid(1), $resolved->tenantId);
        self::assertTrue($resolved->active);
        self::assertSame(
            $resolved->host,
            $addresses->forWebsite($this->uuid(1), $this->uuid(10))?->host,
        );
        self::assertNull($addresses->forWebsite($this->uuid(2), $this->uuid(10)));

        $context = (new PostgresPublicSiteContextFactory($addresses, [], true))->forHost(
            'KLINIK-ONE.SYIFA.MY.',
        );
        self::assertNotNull($context);
        self::assertSame($this->uuid(10), $context->websiteId);
        self::assertSame('https://klinik-one.syifa.my', $context->origin());
        self::assertNull(
            (new PostgresPublicSiteContextFactory($addresses, [], true))->forHost(
                'unknown.syifa.my',
            ),
        );
    }

    private function insertWebsite(string $websiteId, string $tenantId, string $lifecycle): void
    {
        $this->db()->table('websites')->insert([
            'id' => $websiteId,
            'tenant_id' => $tenantId,
            'template_id' => 'SYIFA_ESSENTIAL',
            'lifecycle' => $lifecycle,
            'clinic_name' => 'Clinic',
            'tagline' => null,
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'logo_reference' => null,
            'favicon_reference' => null,
            'contact_email' => 'clinic@example.test',
            'contact_phone' => '+60123456789',
            'address' => 'Kuala Lumpur',
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'tiktok_url' => null,
            'linkedin_url' => null,
            'domain_created_at' => '2026-08-27 00:00:00+00',
            'domain_updated_at' => '2026-08-27 00:00:00+00',
            'version' => 1,
            'created_at' => '2026-08-27 00:00:00+00',
            'updated_at' => '2026-08-27 00:00:00+00',
        ]);
    }

    private function db(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
