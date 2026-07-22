<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
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
        Schema::connection(self::CONNECTION)->dropIfExists('website_assets');
        Schema::connection(self::CONNECTION)->dropIfExists('website_seo_configurations');
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
        $seoMigration = require base_path('database/migrations/website_builder/2026_08_10_000001_create_website_seo_configurations_table.php');
        self::assertInstanceOf(Migration::class, $seoMigration);
        $seoMigration->up();
        $this->migrations[] = $seoMigration;
        $assetMigration = require base_path('database/migrations/website_builder/2026_08_11_000001_create_website_assets_table.php');
        self::assertInstanceOf(Migration::class, $assetMigration);
        $assetMigration->up();
        $this->migrations[] = $assetMigration;
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
        self::assertSame('Klinik Syifa', $loaded->seo()->metaTitle());
        self::assertSame(1, $loaded->seo()->version());
    }

    public function test_seo_configuration_round_trips_with_explicit_columns(): void
    {
        $website = $this->website();
        $website->configureSeo('Klinik Syifa KL', 'Trusted primary care in Kuala Lumpur.', 'clinic, primary care', 'https://clinic.example/about', RobotsDirective::IndexNoFollow, 'Klinik Syifa', 'Book trusted care.', new AssetId($this->uuid(700)), false, $this->at('+1 hour'));
        $this->repository()->save($website);

        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame('Klinik Syifa KL', $loaded->seo()->metaTitle());
        self::assertSame('clinic, primary care', $loaded->seo()->metaKeywords());
        self::assertSame('https://clinic.example/about', $loaded->seo()->canonicalUrl());
        self::assertSame(RobotsDirective::IndexNoFollow, $loaded->seo()->robotsDirective());
        self::assertFalse($loaded->seo()->indexingEnabled());
        self::assertSame(1, $this->db()->table('website_seo_configurations')->where('website_id', $website->id->value)->count());
    }

    public function test_seo_constraints_reject_arbitrary_robots_directive(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $this->expectException(QueryException::class);
        $this->db()->table('website_seo_configurations')->where('website_id', $website->id->value)->update(['robots_directive' => 'all']);
    }

    public function test_stale_seo_write_rolls_back_the_entire_website_transaction(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $this->db()->table('website_seo_configurations')->where('website_id', $website->id->value)->update(['version' => 99]);
        $website->configureSeo('Changed title', 'Changed description', null, null, RobotsDirective::IndexFollow, 'Changed title', 'Changed description', null, true, $this->at('+1 hour'));

        try {
            $this->repository()->save($website);
            self::fail('Expected stale SEO write.');
        } catch (StaleWebsiteWriteException) {
            self::assertSame(1, $website->version());
            self::assertSame(1, $this->db()->table('websites')->where('id', $website->id->value)->value('version'));
            self::assertSame('Klinik Syifa', $this->db()->table('website_seo_configurations')->where('website_id', $website->id->value)->value('meta_title'));
        }
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

    public function test_asset_metadata_and_lifecycle_round_trip(): void
    {
        $website = $this->website();
        $asset = WebsiteAsset::register(new AssetId($this->uuid(800)), new TenantId($this->uuid(1)), 'tenant-1/assets/hero.webp', AssetMimeType::Webp, 4096, 1200, 800, str_repeat('a', 64), $this->at());
        $website->registerAsset($asset, $this->at());
        $website->makeAssetAvailable($asset->id, new AssetAvailabilityEvidence(true, true), $this->at('+1 hour'));
        $this->repository()->save($website);

        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        $stored = $loaded->assets()->asset($asset->id);
        self::assertSame('tenant-1/assets/hero.webp', $stored->storageKey);
        self::assertSame(AssetMimeType::Webp, $stored->mimeType);
        self::assertSame('available', $stored->status()->value);
        self::assertSame(1, $stored->version());
        self::assertSame(1, $this->db()->table('website_assets')->where('website_id', $website->id->value)->count());
    }

    public function test_asset_database_constraints_reject_unknown_mime(): void
    {
        $website = $this->website();
        $asset = WebsiteAsset::register(new AssetId($this->uuid(801)), new TenantId($this->uuid(1)), 'tenant-1/assets/logo.png', AssetMimeType::Png, 1024, 400, 200, str_repeat('b', 64), $this->at());
        $website->registerAsset($asset, $this->at());
        $this->repository()->save($website);

        $this->expectException(QueryException::class);
        $this->db()->table('website_assets')->where('id', $asset->id->value)->update(['mime_type' => 'application/javascript']);
    }

    public function test_asset_database_constraint_rejects_cross_tenant_ownership(): void
    {
        $website = $this->website();
        $asset = WebsiteAsset::register(new AssetId($this->uuid(802)), new TenantId($this->uuid(1)), 'tenant-1/assets/about.png', AssetMimeType::Png, 1024, null, null, str_repeat('c', 64), $this->at());
        $website->registerAsset($asset, $this->at());
        $this->repository()->save($website);

        $this->expectException(QueryException::class);
        $this->db()->table('website_assets')->where('id', $asset->id->value)->update(['tenant_id' => $this->uuid(2)]);
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
        $assetMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $assetMigration);
        $assetMigration->down();
        $seoMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $seoMigration);
        $seoMigration->down();
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
        $seoMigration->up();
        $this->migrations[] = $seoMigration;
        $assetMigration->up();
        $this->migrations[] = $assetMigration;
        $rows = $this->db()->table('website_sections')->where('website_id', $this->uuid(30))->orderBy('display_order')->get();
        self::assertCount(9, $rows);
        self::assertSame(['HERO', 'ABOUT', 'SERVICES', 'DOCTORS', 'TESTIMONIALS', 'GALLERY', 'FAQ', 'CONTACT', 'BOOKING_CTA'], $rows->pluck('section_type')->all());
        self::assertNotContains(false, $rows->pluck('enabled')->map(static fn (mixed $value): bool => (bool) $value)->all());
    }

    public function test_seo_migration_backfills_existing_website_with_safe_defaults(): void
    {
        $this->repository()->save($this->website());
        $assetMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $assetMigration);
        $assetMigration->down();
        $seoMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $seoMigration);
        $seoMigration->down();

        $seoMigration->up();
        $this->migrations[] = $seoMigration;
        $assetMigration->up();
        $this->migrations[] = $assetMigration;
        $row = $this->db()->table('website_seo_configurations')->where('website_id', $this->uuid(3))->first();
        self::assertNotNull($row);
        self::assertSame('Klinik Syifa', $row->meta_title);
        self::assertSame('index,follow', $row->robots_directive);
        self::assertTrue((bool) $row->indexing_enabled);
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
        return new PostgresWebsiteRepository($this->db(), new WebsitePersistenceMapper, new WebsiteSectionPersistenceMapper, new WebsiteSeoConfigurationPersistenceMapper, new WebsiteAssetPersistenceMapper);
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
