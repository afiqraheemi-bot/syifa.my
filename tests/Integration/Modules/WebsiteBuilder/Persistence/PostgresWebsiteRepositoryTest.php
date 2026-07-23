<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicePresentationItem;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicWebsiteRenderModelProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsitePublishedSnapshotReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteSeoSummaryReadAdapter;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\PublishStatusProvider;
use App\Support\Dashboard\Application\Website\SeoStatusProvider;
use App\Support\Dashboard\Application\Website\ThemeInformationProvider;
use App\Support\Dashboard\Application\Website\WebsiteStatusProvider;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WebsitePublicationContentFactory;
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
        foreach (['website_published_business_hours', 'website_published_contact_projections', 'website_published_service_items', 'website_published_faq_entries', 'website_published_gallery_images', 'website_published_testimonials', 'website_published_doctor_profiles', 'website_published_service_references', 'website_published_booking_cta_contents', 'website_published_contact_contents', 'website_published_about_contents', 'website_published_hero_contents', 'website_published_section_contents'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
        Schema::connection(self::CONNECTION)->dropIfExists('website_service_section_items');
        Schema::connection(self::CONNECTION)->dropIfExists('website_publication_history');
        Schema::connection(self::CONNECTION)->dropIfExists('website_published_snapshot_assets');
        Schema::connection(self::CONNECTION)->dropIfExists('website_published_snapshot_sections');
        Schema::connection(self::CONNECTION)->dropIfExists('website_published_snapshots');
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
        $publishingMigration = require base_path('database/migrations/website_builder/2026_08_12_000001_create_website_publishing_tables.php');
        self::assertInstanceOf(Migration::class, $publishingMigration);
        $publishingMigration->up();
        $this->migrations[] = $publishingMigration;
        $contentMigration = require base_path('database/migrations/website_builder/2026_08_13_000001_create_website_published_section_content_tables.php');
        self::assertInstanceOf(Migration::class, $contentMigration);
        $contentMigration->up();
        $this->migrations[] = $contentMigration;
        $servicePresentationMigration = require base_path('database/migrations/website_builder/2026_08_18_000001_create_website_service_section_items.php');
        self::assertInstanceOf(Migration::class, $servicePresentationMigration);
        $servicePresentationMigration->up();
        $this->migrations[] = $servicePresentationMigration;
        $renderingContractMigration = require base_path('database/migrations/website_builder/2026_08_19_000001_complete_public_rendering_contract.php');
        self::assertInstanceOf(Migration::class, $renderingContractMigration);
        $renderingContractMigration->up();
        $this->migrations[] = $renderingContractMigration;
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

    public function test_service_presentation_persists_featured_state_order_and_tenant_lineage(): void
    {
        $website = $this->website();
        $website->configureServicesPresentation([
            new ServicePresentationItem($this->uuid(701), 2),
            new ServicePresentationItem($this->uuid(700), 1, true),
        ], [$this->uuid(700), $this->uuid(701)], $this->at('+1 hour'));
        $this->repository()->save($website);

        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame([$this->uuid(700), $this->uuid(701)], $loaded->servicesPresentation()->serviceReferences());
        self::assertTrue($loaded->servicesPresentation()->items[0]->isFeatured);
        self::assertSame(1, $this->db()->table('website_service_section_items')->where('is_featured', true)->count());
        self::assertSame($website->servicesPresentation()->sectionId()->value, $this->db()->table('website_service_section_items')->value('section_id'));
    }

    public function test_database_enforces_unique_service_order_and_single_featured_item(): void
    {
        $website = $this->website();
        $website->configureServicesPresentation([
            new ServicePresentationItem($this->uuid(700), 1, true),
        ], [$this->uuid(700)], $this->at('+1 hour'));
        $this->repository()->save($website);
        $row = $this->db()->table('website_service_section_items')->first();
        self::assertNotNull($row);

        try {
            $this->db()->table('website_service_section_items')->insert([
                'website_id' => $row->website_id, 'section_id' => $row->section_id,
                'service_id' => $this->uuid(701), 'display_order' => 1, 'is_featured' => false,
            ]);
            self::fail('Duplicate display order must fail.');
        } catch (QueryException) {
            self::assertSame(1, $this->db()->table('website_service_section_items')->count());
        }

        $this->expectException(QueryException::class);
        $this->db()->table('website_service_section_items')->insert([
            'website_id' => $row->website_id, 'section_id' => $row->section_id,
            'service_id' => $this->uuid(702), 'display_order' => 2, 'is_featured' => true,
        ]);
    }

    public function test_service_presentation_migration_backfills_latest_published_order_as_not_featured_and_reverses_safely(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(849)), $this->uuid(900), $this->at('+2 hours'));
        $this->repository()->save($website);

        $renderingContractMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $renderingContractMigration);
        $renderingContractMigration->down();
        $migration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $migration);
        $migration->down();
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('website_service_section_items'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasColumn('website_published_service_references', 'service_id'));

        $migration->up();
        $this->migrations[] = $migration;
        $renderingContractMigration->up();
        $this->migrations[] = $renderingContractMigration;
        $row = $this->db()->table('website_service_section_items')->first();
        self::assertNotNull($row);
        self::assertSame($this->uuid(702), $row->service_id);
        self::assertSame(1, $row->display_order);
        self::assertFalse((bool) $row->is_featured);
    }

    public function test_rendering_contract_migration_applies_and_reverses_without_touching_historic_content(): void
    {
        $migration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $migration);
        $migration->down();

        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('website_published_service_items'));
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('website_published_contact_projections'));
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('website_published_business_hours'));
        self::assertFalse(Schema::connection(self::CONNECTION)->hasColumn('website_published_gallery_images', 'alt_text'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('website_published_contact_contents'));

        $migration->up();
        $this->migrations[] = $migration;
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('website_published_service_items'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('website_published_contact_projections'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('website_published_business_hours'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasColumn('website_published_gallery_images', 'alt_text'));
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

    public function test_publication_snapshot_history_and_children_persist_atomically(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(850)), $this->uuid(900), $this->at('+2 hours'));
        $this->repository()->save($website);

        self::assertSame(1, $this->db()->table('website_published_snapshots')->where('website_id', $website->id->value)->count());
        self::assertSame(9, $this->db()->table('website_published_snapshot_sections')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(9, $this->db()->table('website_published_section_contents')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_service_references')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_service_items')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_doctor_profiles')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_testimonials')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_gallery_images')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_faq_entries')->where('publication_id', $this->uuid(850))->count());
        self::assertSame('Trusted healthcare', $this->db()->table('website_published_hero_contents')->where('publication_id', $this->uuid(850))->value('headline'));
        self::assertSame($this->uuid(702), $this->db()->table('website_published_service_references')->where('publication_id', $this->uuid(850))->value('service_id'));
        self::assertSame('Rawatan Kesihatan Am', $this->db()->table('website_published_service_items')->where('publication_id', $this->uuid(850))->value('display_name'));
        self::assertSame($this->uuid(9990), $this->db()->table('website_published_gallery_images')->where('publication_id', $this->uuid(850))->value('asset_id'));
        self::assertSame('Ruang menunggu klinik yang selesa', $this->db()->table('website_published_gallery_images')->where('publication_id', $this->uuid(850))->value('alt_text'));
        self::assertSame(1, $this->db()->table('website_published_contact_projections')->where('publication_id', $this->uuid(850))->count());
        self::assertSame(1, $this->db()->table('website_published_business_hours')->where('publication_id', $this->uuid(850))->count());
        self::assertSame('When are you open?', $this->db()->table('website_published_faq_entries')->where('publication_id', $this->uuid(850))->value('question'));
        self::assertSame('Book now', $this->db()->table('website_published_booking_cta_contents')->where('publication_id', $this->uuid(850))->value('button_label'));
        self::assertSame(9, $this->db()->table('website_published_section_contents')->where('publication_id', $this->uuid(850))->where('renderable', true)->count());
        self::assertSame(1, $this->db()->table('website_publication_history')->where('website_id', $website->id->value)->count());
        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame(1, $loaded->publishedVersion());
        self::assertSame($this->uuid(900), $loaded->lastPublishedBy());
        self::assertCount(1, $loaded->publicationHistory());
        self::assertCount(9, $loaded->publishedSnapshot()?->sectionContents ?? []);
        self::assertSame('hello@clinic.test', $loaded->publishedSnapshot()?->sectionContents[7]->contactProjection?->email);
        self::assertSame('+60123456789', $loaded->publishedSnapshot()?->sectionContents[7]->contactProjection?->whatsAppNumber);
        self::assertSame('Rawatan Kesihatan Am', $loaded->publishedSnapshot()?->sectionContents[2]->publishedServices[0]->displayName);
        self::assertInstanceOf(GallerySectionContent::class, $loaded->publishedSnapshot()?->sectionContents[5]->content);
        $contentSummary = (new PostgresWebsitePublishedSnapshotReadAdapter($this->db()))->latest($website->id->value);
        self::assertNotNull($contentSummary);
        self::assertCount(9, $contentSummary->sections);
        self::assertSame(
            ['HERO', 'ABOUT', 'SERVICES', 'DOCTORS', 'TESTIMONIALS', 'GALLERY', 'FAQ', 'CONTACT', 'BOOKING_CTA'],
            array_column($contentSummary->sections, 'type'),
        );
        self::assertSame(1, $contentSummary->sections[2]->itemCount);
        self::assertNotContains(false, array_column($contentSummary->sections, 'renderable'));
        self::assertSame([
            ['Trusted healthcare'],
            ['About us'],
            ['Rawatan Kesihatan Am'],
            ['Dr Syifa'],
            ['Patient'],
            ['Ruang menunggu utama'],
            ['When are you open?'],
            ['Kuala Lumpur', '+6012'],
        ], array_map(
            static fn ($section): array => $section->highlights,
            array_slice($contentSummary->sections, 0, 8),
        ));

        $context = new AuthorizationContext('clinic_owner', 'owner-1', $this->uuid(1), 'clinic_owner', 'Owner', 'shared.authenticated-route', []);
        $websiteRead = new PostgresWebsiteReadAdapter($this->db());
        $snapshotRead = new PostgresWebsitePublishedSnapshotReadAdapter($this->db());
        $seoRead = new PostgresWebsiteSeoSummaryReadAdapter($this->db());

        $websiteStatus = (new WebsiteStatusProvider($websiteRead))->provide($context)->data;
        self::assertSame('Published', $websiteStatus['value']);
        self::assertSame('hello@clinic.test · +6012 · Kuala Lumpur', $websiteStatus['detail']);
        self::assertSame('Published', (new PublishStatusProvider($websiteRead, $snapshotRead))->provide($context)->data['value']);
        self::assertSame('SYIFA_ESSENTIAL', (new ThemeInformationProvider($websiteRead))->provide($context)->data['value']);
        self::assertSame('Indexing enabled', (new SeoStatusProvider($websiteRead, $seoRead))->provide($context)->data['value']);
    }

    public function test_public_reader_uses_snapshot_only_and_ignores_later_draft_mutation(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $reader = new PostgresWebsitePublishedSnapshotReadAdapter($this->db());
        self::assertNull($reader->latest($website->id->value));
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(851)), $this->uuid(900), $this->at('+2 hours'));
        $this->repository()->save($website);
        self::assertSame('Klinik Syifa', $reader->latest($website->id->value)?->clinicName);

        $website->updateBranding($this->branding('Mutable Draft'), $this->at('+3 hours'));
        $this->repository()->save($website);
        self::assertSame('Mutable Draft', $this->db()->table('websites')->where('id', $website->id->value)->value('clinic_name'));
        self::assertSame('Klinik Syifa', $reader->latest($website->id->value)?->clinicName);
    }

    public function test_public_delivery_provider_projects_only_the_latest_immutable_snapshot(): void
    {
        $website = $this->website();
        $repository = $this->repository();
        $repository->save($website);
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(852)), $this->uuid(900), $this->at('+2 hours'));
        $repository->save($website);
        $provider = new PostgresPublicWebsiteRenderModelProvider($repository, new PublicWebsiteRenderProjector);

        $render = $provider->find(new PublicSiteContext('https', 'clinic.example', websiteId: $website->id->value));

        self::assertNotNull($render);
        self::assertSame('Klinik Syifa', $render->branding->clinicName);
        self::assertInstanceOf(ServicesSectionRenderModel::class, $render->sections[2]);
        self::assertSame('Rawatan Kesihatan Am', $render->sections[2]->services[0]->displayName);
        self::assertNull($provider->find(new PublicSiteContext('https', 'missing.example', websiteId: $this->uuid(999))));
    }

    public function test_republish_inserts_new_version_and_preserves_previous_snapshot(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(853)), $this->uuid(900), $this->at('+2 hours'));
        $this->repository()->save($website);
        $website->updateBranding($this->branding('Second Draft'), $this->at('+3 hours'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(854)), $this->uuid(901), $this->at('+4 hours'));
        $this->repository()->save($website);

        self::assertSame(2, $this->db()->table('website_published_snapshots')->where('website_id', $website->id->value)->count());
        self::assertSame('Klinik Syifa', $this->db()->table('website_published_snapshots')->where('publication_id', $this->uuid(853))->value('clinic_name'));
        self::assertSame('Second Draft', $this->db()->table('website_published_snapshots')->where('publication_id', $this->uuid(854))->value('clinic_name'));
        self::assertSame(2, $this->db()->table('website_publication_history')->where('website_id', $website->id->value)->count());
        self::assertSame(18, $this->db()->table('website_published_section_contents')->where('website_id', $website->id->value)->count());
        self::assertSame('Klinik Syifa', $this->db()->table('website_published_contact_projections')->where('website_published_contact_projections.publication_id', $this->uuid(853))->join('website_published_snapshots', 'website_published_contact_projections.publication_id', '=', 'website_published_snapshots.publication_id')->value('clinic_name'));
        $loaded = $this->repository()->findByTenant(new TenantId($this->uuid(1)));
        self::assertNotNull($loaded);
        self::assertSame(2, $loaded->publishedVersion());
        self::assertCount(2, $loaded->publicationHistory());
    }

    public function test_publication_constraint_failure_rolls_back_snapshot_and_website_write(): void
    {
        $website = $this->website();
        $this->repository()->save($website);
        $this->db()->table('website_publication_history')->insert([
            'publication_id' => $this->uuid(899), 'website_id' => $website->id->value, 'published_version' => 1,
            'published_at' => $this->at(), 'published_by' => $this->uuid(900), 'result' => 'published', 'created_at' => $this->at(),
        ]);
        $website->readyForReview($this->at('+1 hour'));
        $website->publish($this->publicationEvidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(852)), $this->uuid(900), $this->at('+2 hours'));

        try {
            $this->repository()->save($website);
            self::fail('Expected publication history version conflict.');
        } catch (QueryException) {
            self::assertSame(0, $this->db()->table('website_published_snapshots')->where('publication_id', $this->uuid(852))->count());
            self::assertSame(0, $this->db()->table('website_published_section_contents')->where('publication_id', $this->uuid(852))->count());
            self::assertSame('draft', $this->db()->table('websites')->where('id', $website->id->value)->value('lifecycle'));
        }
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
        $renderingContractMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $renderingContractMigration);
        $renderingContractMigration->down();
        $servicePresentationMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $servicePresentationMigration);
        $servicePresentationMigration->down();
        $contentMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $contentMigration);
        $contentMigration->down();
        $publishingMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $publishingMigration);
        $publishingMigration->down();
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
        $publishingMigration->up();
        $this->migrations[] = $publishingMigration;
        $contentMigration->up();
        $this->migrations[] = $contentMigration;
        $servicePresentationMigration->up();
        $this->migrations[] = $servicePresentationMigration;
        $renderingContractMigration->up();
        $this->migrations[] = $renderingContractMigration;
        $rows = $this->db()->table('website_sections')->where('website_id', $this->uuid(30))->orderBy('display_order')->get();
        self::assertCount(9, $rows);
        self::assertSame(['HERO', 'ABOUT', 'SERVICES', 'DOCTORS', 'TESTIMONIALS', 'GALLERY', 'FAQ', 'CONTACT', 'BOOKING_CTA'], $rows->pluck('section_type')->all());
        self::assertNotContains(false, $rows->pluck('enabled')->map(static fn (mixed $value): bool => (bool) $value)->all());
    }

    public function test_seo_migration_backfills_existing_website_with_safe_defaults(): void
    {
        $this->repository()->save($this->website());
        $renderingContractMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $renderingContractMigration);
        $renderingContractMigration->down();
        $servicePresentationMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $servicePresentationMigration);
        $servicePresentationMigration->down();
        $contentMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $contentMigration);
        $contentMigration->down();
        $publishingMigration = array_pop($this->migrations);
        self::assertInstanceOf(Migration::class, $publishingMigration);
        $publishingMigration->down();
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
        $publishingMigration->up();
        $this->migrations[] = $publishingMigration;
        $contentMigration->up();
        $this->migrations[] = $contentMigration;
        $servicePresentationMigration->up();
        $this->migrations[] = $servicePresentationMigration;
        $renderingContractMigration->up();
        $this->migrations[] = $renderingContractMigration;
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

    private function publicationEvidence(): WebsitePublicationEvidence
    {
        return new WebsitePublicationEvidence(true, true);
    }

    private function readiness(): WebsitePublicationReadiness
    {
        return new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('d', 64));
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
