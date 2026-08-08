<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Persistence;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftService;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteService;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\WebsitePublicationContentFactory;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReadyForReviewService;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\WebsitePublicationReadinessEvaluator;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingServiceOption;
use App\Modules\WebsiteBuilder\Contracts\Publication\WebsitePublicationApprovalReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqEntry;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GalleryImage;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualDoctorProfile;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualTestimonial;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicePresentationItem;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicSiteContextFactory;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicWebsiteRenderModelProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteDraftRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsitePublicAddressRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Transactions\PostgresWebsitePublicationTransaction;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresWebsiteDraftRepositoryTest extends TestCase
{
    private const string CONNECTION = 'website_draft_postgres_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Closure(): void> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('WEBSITE_POSTGRES_TEST_DSN')
            ?: getenv('CLINIC_POSTGRES_TEST_DSN')
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
            '2026_08_08_000001_create_website_sections_table.php',
            '2026_08_10_000001_create_website_seo_configurations_table.php',
            '2026_08_11_000001_create_website_assets_table.php',
            '2026_08_12_000001_create_website_publishing_tables.php',
            '2026_08_13_000001_create_website_published_section_content_tables.php',
            '2026_08_18_000001_create_website_service_section_items.php',
            '2026_08_19_000001_complete_public_rendering_contract.php',
            '2026_08_26_000001_create_website_draft_contents.php',
            '2026_08_27_000001_create_website_public_hosts.php',
            '2026_09_06_000001_add_logo_display_size_to_websites.php',
            '2026_09_08_000001_add_whatsapp_button_style_to_websites.php',
        ] as $file) {
            $migration = require base_path('database/migrations/website_builder/'.$file);
            self::assertIsObject($migration);
            self::assertTrue(method_exists($migration, 'up'));
            self::assertTrue(method_exists($migration, 'down'));
            $migration->up();
            $this->migrations[] = Closure::fromCallable([$migration, 'down']);
        }
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

    public function test_round_trip_is_tenant_scoped_versioned_and_keeps_published_content_untouched(): void
    {
        $website = Website::create(
            new WebsiteId($this->uuid(3)),
            new TenantId($this->uuid(1)),
            TemplateId::SyifaEssential,
            new WebsiteBranding(
                'Klinik Syifa',
                null,
                '#112233',
                '#AABBCC',
                null,
                null,
                'hello@clinic.test',
                '+6012',
                'Kuala Lumpur',
            ),
            array_map(fn (int $number): SectionId => new SectionId($this->uuid($number)), range(101, 109)),
            new DateTimeImmutable('2026-08-26T00:00:00Z'),
        );
        $websites = new PostgresWebsiteRepository(
            $this->db(),
            new WebsitePersistenceMapper,
            new WebsiteSectionPersistenceMapper,
            new WebsiteSeoConfigurationPersistenceMapper,
            new WebsiteAssetPersistenceMapper,
        );
        $websites->save($website);
        $this->db()->table('website_assets')->insert(array_map(
            fn (int $suffix): array => [
                'id' => $this->uuid($suffix),
                'website_id' => $website->id->value,
                'tenant_id' => $website->tenantId->value,
                'storage_key' => 'website/'.$website->id->value.'/image-'.$suffix.'.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 1024,
                'width' => 1200,
                'height' => 800,
                'checksum' => str_repeat((string) ($suffix - 49), 64),
                'status' => 'available',
                'domain_created_at' => '2026-08-26 00:00:00+00',
                'domain_updated_at' => '2026-08-26 00:00:00+00',
                'version' => 1,
                'created_at' => '2026-08-26 00:00:00+00',
                'updated_at' => '2026-08-26 00:00:00+00',
            ],
            [50, 51],
        ));
        $drafts = new PostgresWebsiteDraftRepository($this->db(), new WebsiteDraftSectionCodec);

        $draft = $drafts->find($website->tenantId, $website->id);
        self::assertNotNull($draft);
        self::assertSame(1, $draft->version);
        self::assertCount(9, $draft->sections);
        self::assertNull($drafts->find(new TenantId($this->uuid(2)), $website->id));

        $sections = $draft->sections;
        self::assertInstanceOf(HeroSectionContent::class, $sections[0]);
        $sections[0] = new HeroSectionContent(
            $sections[0]->sectionId(),
            'Trusted clinic care',
            'Book with confidence',
            'Book now',
            '/booking',
            heroImageReference: new AssetId($this->uuid(50)),
        );
        $sections[1] = new AboutSectionContent(
            $sections[1]->sectionId(),
            'About Klinik Syifa',
            'Compassionate care for every family.',
            new AssetId($this->uuid(50)),
        );
        $sections[2] = new ServicesSectionContent($sections[2]->sectionId(), [
            new ServicePresentationItem($this->uuid(61), 2),
            new ServicePresentationItem($this->uuid(60), 1, true),
        ]);
        $sections[3] = new DoctorsSectionContent($sections[3]->sectionId(), [
            new ManualDoctorProfile(
                $this->uuid(71),
                'Dr Second',
                'Family Medicine',
                false,
                new AssetId($this->uuid(50)),
            ),
            new ManualDoctorProfile($this->uuid(70), 'Dr First'),
        ]);
        $sections[4] = new TestimonialsSectionContent($sections[4]->sectionId(), [
            new ManualTestimonial(
                $this->uuid(91),
                'The team made every visit comfortable.',
                'Patient Two',
            ),
            new ManualTestimonial(
                $this->uuid(90),
                'Professional and compassionate care.',
                'Patient One',
                true,
            ),
        ]);
        $sections[5] = new GallerySectionContent($sections[5]->sectionId(), [
            new GalleryImage(
                $this->uuid(81),
                new AssetId($this->uuid(51)),
                'Consultation room',
                'A comfortable consultation room.',
            ),
            new GalleryImage(
                $this->uuid(80),
                new AssetId($this->uuid(50)),
                decorative: true,
            ),
        ]);
        $sections[6] = new FaqSectionContent($sections[6]->sectionId(), [
            new FaqEntry(
                $this->uuid(101),
                'Do I need an appointment?',
                'Appointments are recommended.',
            ),
            new FaqEntry(
                $this->uuid(100),
                'When are you open?',
                'We are open every weekday.',
            ),
        ]);
        $sections[8] = new BookingCtaSectionContent(
            $sections[8]->sectionId(),
            'Ready to book?',
            'Choose a suitable appointment time.',
            'Book now',
        );
        $saved = $drafts->save(new WebsiteDraftContent(
            $draft->websiteId,
            $draft->tenantId,
            $draft->version,
            array_values($sections),
        ), 1);

        self::assertSame(2, $saved->version);
        $reloaded = $drafts->find($website->tenantId, $website->id);
        self::assertNotNull($reloaded);
        self::assertInstanceOf(HeroSectionContent::class, $reloaded->sections[0]);
        self::assertSame('Trusted clinic care', $reloaded->sections[0]->headline);
        self::assertSame($this->uuid(50), $reloaded->sections[0]->heroImageReference?->value);
        self::assertInstanceOf(AboutSectionContent::class, $reloaded->sections[1]);
        self::assertSame('About Klinik Syifa', $reloaded->sections[1]->heading);
        self::assertSame($this->uuid(50), $reloaded->sections[1]->imageReference?->value);
        self::assertInstanceOf(ServicesSectionContent::class, $reloaded->sections[2]);
        self::assertSame(
            [$this->uuid(60), $this->uuid(61)],
            $reloaded->sections[2]->serviceReferences(),
        );
        self::assertTrue($reloaded->sections[2]->items[0]->isFeatured);
        self::assertInstanceOf(DoctorsSectionContent::class, $reloaded->sections[3]);
        self::assertSame(
            [$this->uuid(71), $this->uuid(70)],
            array_map(
                static fn (ManualDoctorProfile $profile): string => $profile->id,
                $reloaded->sections[3]->profiles,
            ),
        );
        self::assertSame(
            $this->uuid(50),
            $reloaded->sections[3]->profiles[0]->photo?->value,
        );
        self::assertInstanceOf(TestimonialsSectionContent::class, $reloaded->sections[4]);
        self::assertSame(
            [$this->uuid(91), $this->uuid(90)],
            array_map(
                static fn (ManualTestimonial $testimonial): string => $testimonial->id,
                $reloaded->sections[4]->testimonials,
            ),
        );
        self::assertTrue($reloaded->sections[4]->testimonials[1]->featured);
        self::assertInstanceOf(GallerySectionContent::class, $reloaded->sections[5]);
        self::assertSame(
            [$this->uuid(51), $this->uuid(50)],
            array_map(
                static fn (GalleryImage $image): string => $image->imageReference->value,
                $reloaded->sections[5]->images,
            ),
        );
        self::assertTrue($reloaded->sections[5]->images[1]->decorative);
        self::assertInstanceOf(FaqSectionContent::class, $reloaded->sections[6]);
        self::assertSame(
            [$this->uuid(101), $this->uuid(100)],
            array_map(
                static fn (FaqEntry $entry): string => $entry->id,
                $reloaded->sections[6]->entries,
            ),
        );
        self::assertSame(
            'Appointments are recommended.',
            $reloaded->sections[6]->entries[0]->answer,
        );
        self::assertInstanceOf(BookingCtaSectionContent::class, $reloaded->sections[8]);
        self::assertSame('Ready to book?', $reloaded->sections[8]->heading);
        self::assertSame('Book now', $reloaded->sections[8]->buttonLabel);

        $clinic = Clinic::create(
            new ClinicId($this->uuid(210)),
            $website->tenantId,
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([]),
            new DateTimeImmutable('2026-08-26T00:00:00Z'),
        );
        $clinicRepository = new DraftPreviewClinicRepository($clinic);
        $bookingConfiguration = new DraftPreviewBookingConfiguration([
            new PublicBookingServiceOption($this->uuid(60), 'Consultation', false),
            new PublicBookingServiceOption($this->uuid(61), 'Vaccination', false),
        ]);
        $preview = new PreviewWebsiteDraftService(
            $websites,
            $drafts,
            $clinicRepository,
            $bookingConfiguration,
            new WebsiteAuthorization,
        );
        $previewed = $preview->handle(new PreviewWebsiteDraftCommand(
            new WebsiteAuthorizationContext(
                $this->uuid(200),
                'website_designer',
                assignedTenantId: $website->tenantId->value,
            ),
            $website->tenantId->value,
            $website->id->value,
        ));
        self::assertSame('SYIFA_ESSENTIAL', $previewed->website->templateId);
        self::assertInstanceOf(HeroSectionRenderModel::class, $previewed->sections[0]);
        self::assertSame('Trusted clinic care', $previewed->sections[0]->headline);
        self::assertSame('noindex,nofollow', $previewed->seo->robotsDirective);
        $websiteAfterPreview = $websites->findById($website->tenantId, $website->id);
        self::assertNotNull($websiteAfterPreview);
        self::assertSame(
            WebsiteLifecycle::Draft,
            $websiteAfterPreview->lifecycle(),
        );
        self::assertSame(0, $this->db()->table('website_published_snapshots')->count());

        $review = new ReadyForReviewService(
            $websites,
            $drafts,
            new DraftIntegrationActiveServices([$this->uuid(60), $this->uuid(61)]),
            new WebsiteAuthorization,
            new WebsitePublicationReadinessEvaluator(new WebsiteDraftSectionCodec),
        );
        $reviewed = $review->handle(new ReadyForReviewCommand(
            new WebsiteAuthorizationContext(
                $this->uuid(200),
                'website_designer',
                assignedTenantId: $website->tenantId->value,
            ),
            $website->tenantId->value,
            $website->id->value,
            $websiteAfterPreview->version(),
            $reloaded->version,
        ));
        self::assertSame('ready_for_review', $reviewed->toArray()['lifecycle']);
        $websiteAfterReview = $websites->findById($website->tenantId, $website->id);
        self::assertNotNull($websiteAfterReview);
        self::assertSame(
            WebsiteLifecycle::ReadyForReview,
            $websiteAfterReview->lifecycle(),
        );
        self::assertSame(0, $this->db()->table('website_published_snapshots')->count());
        $authoritativeDraft = $drafts->find($website->tenantId, $website->id);
        self::assertNotNull($authoritativeDraft);
        $addresses = new PostgresWebsitePublicAddressRepository($this->db());
        $addresses->reservePrimary(
            $this->uuid(219),
            $website->tenantId->value,
            $website->id->value,
            'klinik-syifa.syifa.my',
            new DateTimeImmutable('2026-08-26T02:00:00Z'),
        );

        $publisher = new PublishWebsiteService(
            $websites,
            $drafts,
            $clinicRepository,
            $bookingConfiguration,
            new DraftActiveSubscription,
            $addresses,
            new PostgresWebsitePublicationTransaction($this->db()),
            new WebsiteAuthorization,
            new WebsitePublicationReadinessEvaluator(new WebsiteDraftSectionCodec),
            new WebsitePublicationContentFactory,
            new DraftApprovedWebsitePublication,
        );
        $published = $publisher->handle(new PublishWebsiteCommand(
            new WebsiteAuthorizationContext(
                $this->uuid(200),
                'website_designer',
                assignedTenantId: $website->tenantId->value,
            ),
            $website->tenantId->value,
            $website->id->value,
            $this->uuid(220),
            $websiteAfterReview->version(),
            $authoritativeDraft->version,
        ));
        self::assertSame(1, $published->publishedVersion);
        self::assertSame(WebsiteLifecycle::Published->value, $published->lifecycle);
        self::assertSame(1, $this->db()->table('website_published_snapshots')->count());
        self::assertSame(1, $this->db()->table('website_publication_history')->count());
        self::assertNotNull(
            $this->db()->table('website_public_hosts')->value('activated_at'),
        );
        self::assertSame(9, $this->db()->table('website_published_section_contents')->count());
        $publishedWebsite = $websites->findById($website->tenantId, $website->id);
        $publishedHero = $publishedWebsite?->publishedSnapshot()?->sectionContents[0]->content;
        self::assertInstanceOf(HeroSectionContent::class, $publishedHero);
        self::assertSame('Trusted clinic care', $publishedHero->headline);
        $publishedSnapshot = $publishedWebsite?->publishedSnapshot();
        self::assertNotNull($publishedSnapshot);
        $publicRender = (new PublicWebsiteRenderProjector)->project($publishedSnapshot);
        $publicHero = $publicRender->sections[0];
        self::assertInstanceOf(HeroSectionRenderModel::class, $publicHero);
        self::assertSame('Trusted clinic care', $publicHero->headline);
        $publicContext = (new PostgresPublicSiteContextFactory(
            $addresses,
            new DraftActiveSubscription,
            [],
            true,
        ))->forHost('KLINIK-SYIFA.SYIFA.MY');
        self::assertNotNull($publicContext);
        $hostRendered = (new PostgresPublicWebsiteRenderModelProvider(
            $websites,
            new PublicWebsiteRenderProjector,
            new DraftActiveSubscription,
        ))->find($publicContext);
        self::assertNotNull($hostRendered);
        self::assertInstanceOf(HeroSectionRenderModel::class, $hostRendered->sections[0]);
        self::assertSame('Trusted clinic care', $hostRendered->sections[0]->headline);

        $invalidSections = $reloaded->sections;
        $invalidSections[5] = new GallerySectionContent($invalidSections[5]->sectionId(), [
            new GalleryImage(
                $this->uuid(82),
                new AssetId($this->uuid(99)),
            ),
        ]);
        try {
            $drafts->save(new WebsiteDraftContent(
                $reloaded->websiteId,
                $reloaded->tenantId,
                $reloaded->version,
                $invalidSections,
            ), 2);
            self::fail('Expected an asset outside the assigned Website to be rejected.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(2, $drafts->find($website->tenantId, $website->id)?->version);
        }

        $this->expectException(StaleWebsiteWriteException::class);
        $drafts->save($saved, 1);
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

final readonly class DraftIntegrationActiveServices implements ActiveServiceReferenceReadInterface
{
    /** @param list<string> $serviceIds */
    public function __construct(private array $serviceIds) {}

    public function forTenant(string $tenantId): array
    {
        return $this->serviceIds;
    }
}

final readonly class DraftActiveSubscription implements SubscriptionSummaryReadInterface
{
    public function summary(string $trustedTenantId): SubscriptionSummaryData
    {
        return new SubscriptionSummaryData('active', '2099-12-31');
    }
}

final readonly class DraftApprovedWebsitePublication implements WebsitePublicationApprovalReadInterface
{
    public function isApproved(
        string $tenantId,
        string $websiteId,
        int $websiteVersion,
        int $draftVersion,
    ): bool {
        return true;
    }
}

final readonly class DraftPreviewClinicRepository implements ClinicRepositoryInterface
{
    public function __construct(private Clinic $clinic) {}

    public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic
    {
        return $tenantId->value === $this->clinic->tenantId->value
            && $clinicId->value === $this->clinic->id->value
                ? $this->clinic
                : null;
    }

    public function findByTenantId(TenantId $tenantId): ?Clinic
    {
        return $tenantId->value === $this->clinic->tenantId->value ? $this->clinic : null;
    }

    public function save(Clinic $clinic): void
    {
        throw new \LogicException('Draft Preview must not persist Clinic state.');
    }
}

final readonly class DraftPreviewBookingConfiguration implements PublicBookingFormConfigurationReaderInterface
{
    /** @param list<PublicBookingServiceOption> $services */
    public function __construct(private array $services) {}

    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormConfiguration
    {
        return new PublicBookingFormConfiguration(
            true,
            false,
            false,
            false,
            $this->services,
        );
    }
}
