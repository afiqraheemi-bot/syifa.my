<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteReadAdapter;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresWebsiteRepositoryTest extends TestCase
{
    private const string CONNECTION = 'website_core_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?Migration $migration = null;

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
        $this->db()->table('tenants')->insert([['id' => $this->uuid(1)], ['id' => $this->uuid(2)]]);
        $migration = require base_path('database/migrations/website_builder/2026_08_07_000001_create_websites_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $this->migration = $migration;
    }

    protected function tearDown(): void
    {
        $this->migration?->down();
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_round_trip_and_tenant_scoped_reads(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame(1, $loaded->version());
        self::assertSame(TemplateId::SyifaEssential, $loaded->templateId());
        self::assertSame('Klinik Syifa', $loaded->branding()->clinicName);
        self::assertNull($this->repository()->findByTenant(new TenantId($this->uuid(2))));
        $read = new PostgresWebsiteReadAdapter($this->db());
        self::assertSame('SYIFA_ESSENTIAL', $read->summary($this->uuid(1))?->templateId);
        self::assertSame('#112233', $read->detail($this->uuid(1))?->primaryColor);
        self::assertNull($read->detail($this->uuid(2)));
    }

    public function test_unique_tenant_constraint_enforces_one_website(): void
    {
        $this->repository()->save($this->website());
        $this->expectException(QueryException::class);
        $this->repository()->save($this->website(id: 9));
    }

    public function test_optimistic_locking_rejects_stale_update(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $first = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        $stale = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($first);
        self::assertNotNull($stale);
        $first->updateBranding($this->branding('First'), $this->at('+1 hour'));
        $this->repository()->save($first);
        $stale->updateBranding($this->branding('Stale'), $this->at('+2 hours'));
        $this->expectException(StaleWebsiteWriteException::class);
        $this->repository()->save($stale);
    }

    public function test_database_constraints_reject_invalid_template_and_migration_rolls_back(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        try {
            $this->db()->table('websites')->where('id', $website->id->value)->update(['template_id' => 'UNKNOWN']);
            self::fail('Expected template constraint.');
        } catch (QueryException) {
            self::assertSame('SYIFA_ESSENTIAL', $this->db()->table('websites')->value('template_id'));
        }
        $this->migration?->down();
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('websites'));
        $this->migration = null;
    }

    private function repository(): WebsiteRepositoryInterface
    {
        return new PostgresWebsiteRepository($this->db(), new WebsitePersistenceMapper);
    }

    private function website(int $id = 3): Website
    {
        return Website::create(new WebsiteId($this->uuid($id)), new TenantId($this->uuid(1)), TemplateId::SyifaEssential, $this->branding(), $this->at());
    }

    private function branding(string $name = 'Klinik Syifa'): WebsiteBranding
    {
        return new WebsiteBranding($name, null, '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur', ['instagram' => 'https://instagram.com/clinic']);
    }

    private function db(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-07T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
