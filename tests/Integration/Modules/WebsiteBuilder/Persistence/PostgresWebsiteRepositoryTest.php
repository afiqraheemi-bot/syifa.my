<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
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

    /** @var list<Migration> */
    private array $migrations = [];

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
        Schema::connection(self::CONNECTION)->dropIfExists('website_sections');
        Schema::connection(self::CONNECTION)->dropIfExists('websites');
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        Schema::connection(self::CONNECTION)->create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
        $this->db()->table('tenants')->insert([['id' => $this->uuid(1)], ['id' => $this->uuid(2)]]);
        $migration = require base_path('database/migrations/website_builder/2026_08_07_000001_create_websites_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $this->migrations[] = $migration;
        $sectionsMigration = require base_path('database/migrations/website_builder/2026_08_08_000001_create_website_sections_table.php');
        self::assertInstanceOf(Migration::class, $sectionsMigration);
        $sectionsMigration->up();
        $this->migrations[] = $sectionsMigration;
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
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
        self::assertCount(9, $loaded->sections()->sections());
        self::assertSame(9, $this->db()->table('website_sections')->where('website_id', $website->id->value)->count());
    }

    public function test_section_state_round_trips_with_deterministic_order(): void
    {
        $website = $this->website();
        $hero = $website->sections()->sections()[0];
        $website->disableSection($hero->id, $this->at('+1 hour'));
        $website->reorderSection($hero->id, new SectionDisplayOrder(4), $this->at('+2 hours'));
        $this->repository()->save($website);

        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame($hero->id->value, $loaded->sections()->sections()[3]->id->value);
        self::assertFalse($loaded->sections()->sections()[3]->enabled());
        self::assertSame(range(1, 9), array_map(static fn ($section): int => $section->displayOrder()->value, $loaded->sections()->sections()));
    }

    public function test_section_unique_type_and_order_constraints_are_enforced(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $hero = $this->db()->table('website_sections')->where('website_id', $website->id->value)->where('section_type', 'HERO')->first();
        self::assertNotNull($hero);

        $this->expectException(QueryException::class);
        $this->db()->table('website_sections')->insert([
            'id' => $this->uuid(999), 'website_id' => $website->id->value, 'section_type' => 'HERO', 'display_order' => 9,
            'enabled' => true, 'domain_created_at' => $this->at(), 'domain_updated_at' => $this->at(), 'version' => 1,
            'created_at' => $this->at(), 'updated_at' => $this->at(),
        ]);
    }

    public function test_migration_backfills_all_sections_for_an_existing_website(): void
    {
        $sectionsMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $sectionsMigration);
        $sectionsMigration->down();
        $at = $this->at();
        $this->db()->table('websites')->insert([
            'id' => $this->uuid(30), 'tenant_id' => $this->uuid(1), 'template_id' => 'SYIFA_ESSENTIAL', 'lifecycle' => 'draft',
            'clinic_name' => 'Existing Clinic', 'tagline' => null, 'primary_color' => '#112233', 'secondary_color' => '#AABBCC',
            'logo_reference' => null, 'favicon_reference' => null, 'contact_email' => 'existing@clinic.test', 'contact_phone' => '+6012',
            'address' => 'Kuala Lumpur', 'facebook_url' => null, 'instagram_url' => null, 'youtube_url' => null, 'tiktok_url' => null,
            'linkedin_url' => null, 'domain_created_at' => $at, 'domain_updated_at' => $at, 'version' => 1, 'created_at' => $at, 'updated_at' => $at,
        ]);

        $sectionsMigration->up();
        $this->migrations[] = $sectionsMigration;
        $rows = $this->db()->table('website_sections')->where('website_id', $this->uuid(30))->orderBy('display_order')->get();
        self::assertCount(9, $rows);
        self::assertSame(['HERO', 'ABOUT', 'SERVICES', 'DOCTORS', 'TESTIMONIALS', 'GALLERY', 'FAQ', 'CONTACT', 'BOOKING_CTA'], $rows->pluck('section_type')->all());
        self::assertNotContains(false, $rows->pluck('enabled')->map(static fn (mixed $value): bool => (bool) $value)->all());
    }

    public function test_section_write_rolls_back_with_stale_website(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $first = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        $stale = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($first);
        self::assertNotNull($stale);
        $first->disableSection($first->sections()->sections()[0]->id, $this->at('+1 hour'));
        $this->repository()->save($first);
        $stale->disableSection($stale->sections()->sections()[1]->id, $this->at('+2 hours'));

        try {
            $this->repository()->save($stale);
            self::fail('Expected stale aggregate write.');
        } catch (StaleWebsiteWriteException) {
            self::assertTrue((bool) $this->db()->table('website_sections')->where('section_type', 'ABOUT')->value('enabled'));
        }
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
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('websites'));
        $this->migrations = [];
    }

    private function repository(): WebsiteRepositoryInterface
    {
        return new PostgresWebsiteRepository($this->db(), new WebsitePersistenceMapper, new WebsiteSectionPersistenceMapper);
    }

    private function website(int $id = 3): Website
    {
        return Website::create(new WebsiteId($this->uuid($id)), new TenantId($this->uuid(1)), TemplateId::SyifaEssential, $this->branding(), $this->sectionIds($id), $this->at());
    }

    /** @return list<SectionId> */
    private function sectionIds(int $seed): array
    {
        return array_map(fn (int $offset): SectionId => new SectionId($this->uuid(($seed * 100) + $offset)), range(1, 9));
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
