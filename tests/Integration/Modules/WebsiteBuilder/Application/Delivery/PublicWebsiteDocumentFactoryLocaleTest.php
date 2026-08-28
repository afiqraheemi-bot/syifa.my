<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId as TenantManagementTenantId;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Queries\PostgresClinicOwnerLocalePreferenceReadAdapter;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicContentLanguage;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId as WebsiteBuilderTenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\ConfiguredPlatformLegalContentProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\OriginPublicAssetUrlResolver;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WebsitePublicationContentFactory;
use Tests\TestCase;

final class PublicWebsiteDocumentFactoryLocaleTest extends TestCase
{
    private const string CONNECTION = 'public_website_document_factory_locale_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('TENANT_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires TENANT_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        Schema::dropIfExists('clinic_owner_authorities');
        Schema::dropIfExists('tenants');

        foreach ([
            '2026_07_13_000001_create_tenant_aggregate_tables.php',
            '2026_07_13_000002_add_clinic_owner_credential_state.php',
            '2026_07_13_000003_add_tenant_admin_routing_label.php',
            '2026_08_21_000001_add_remember_token_to_clinic_owner_authorities.php',
            '2026_08_28_000001_add_preferred_locale_to_clinic_owner_authorities.php',
            '2026_08_28_000002_make_preferred_locale_nullable_on_clinic_owner_authorities.php',
        ] as $file) {
            $migration = require base_path('database/migrations/tenant_management/'.$file);
            self::assertInstanceOf(Migration::class, $migration);
            $this->migrations[] = $migration;
            $migration->up();
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_a_published_document_falls_back_to_auto_detection_when_the_owner_never_chose_a_language(): void
    {
        $tenantId = $this->provisionOwner();

        $document = $this->makeDocument($tenantId);

        self::assertSame(PublicContentLanguage::MALAY, $document->language);
    }

    public function test_a_published_document_uses_the_owners_explicit_choice_over_auto_detected_content(): void
    {
        $tenantId = $this->provisionOwner();
        $this->connection()->table('clinic_owner_authorities')->where('tenant_id', $tenantId)->update(['preferred_locale' => 'en']);

        $document = $this->makeDocument($tenantId);

        self::assertSame(PublicContentLanguage::ENGLISH, $document->language);
    }

    private function makeDocument(string $tenantId)
    {
        $website = Website::create(
            new WebsiteId($this->uuid(1)),
            new WebsiteBuilderTenantId($tenantId),
            TemplateId::SyifaEssential,
            new WebsiteBranding('Klinik Anda', 'Klinik keluarga yang dipercayai untuk anda dan keluarga', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'),
            array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)),
            new DateTimeImmutable('2026-08-19T00:00:00Z'),
        );
        $website->readyForReview(new DateTimeImmutable('2026-08-19T01:00:00Z'));
        $website->publish(
            new WebsitePublicationEvidence(true, true),
            new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)),
            WebsitePublicationContentFactory::complete($website),
            new PublicationId($this->uuid(80)),
            $this->uuid(90),
            new DateTimeImmutable('2026-08-19T02:00:00Z'),
        );
        $model = (new PublicWebsiteRenderProjector)->project($website->publishedSnapshot());
        $context = new PublicSiteContext('https', 'clinic.example', websiteId: $this->uuid(1), tenantId: $tenantId);
        $factory = new PublicWebsiteDocumentFactory(
            new OriginPublicAssetUrlResolver('https://cdn.example'),
            new ConfiguredPlatformLegalContentProvider([]),
            new PostgresClinicOwnerLocalePreferenceReadAdapter($this->connection()),
        );

        return $factory->make($model, $context);
    }

    private function provisionOwner(): string
    {
        $tenantId = $this->uuid(1);
        $authorityId = $this->uuid(11);
        $clinicOwnerIdentityId = $this->uuid(21);

        $tenant = Tenant::provision(new TenantManagementTenantId($tenantId), $this->time());
        $tenant->establishClinicOwnerAuthority(
            new ClinicOwnerAuthorityId($authorityId),
            new ClinicOwnerIdentity(new ClinicOwnerIdentityId($clinicOwnerIdentityId), new ClinicOwnerEmail('owner@example.test'), new ClinicOwnerName('Dr Aisyah')),
            $this->time(),
        );
        $tenant->activate($this->time());
        $tenant->changeClinicOwnerPasswordHash(new ClinicOwnerAuthorityId($authorityId), Hash::make('Synthetic-Password-123!'));
        $tenant->verifyClinicOwnerEmail(new ClinicOwnerAuthorityId($authorityId), $this->time());
        (new PostgresTenantRepository($this->connection(), new TenantPersistenceMapper))->save($tenant);

        return $tenantId;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T10:00:00+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
