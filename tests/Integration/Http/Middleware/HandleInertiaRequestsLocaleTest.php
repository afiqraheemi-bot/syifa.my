<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Modules\Onboarding\Contracts\Administration\PendingOnboardingJobsReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\PendingWebsiteDesignerTasksReadInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HandleInertiaRequestsLocaleTest extends TestCase
{
    private const string CONNECTION = 'handle_inertia_requests_locale_integration';

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
        app()->setLocale('en');
        parent::tearDown();
    }

    public function test_it_shares_a_clinic_owners_stored_preferred_locale_and_applies_it_application_wide(): void
    {
        [$tenantId, $authorityId] = $this->provisionOwner();
        $this->connection()->table('clinic_owner_authorities')->where('id', $authorityId)->update(['preferred_locale' => 'ms']);

        $context = new AuthorizationContext('clinic_owner', $authorityId, $tenantId, 'clinic_owner', 'Dr Aisyah', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard', 'GET');
        $request->attributes->set(AuthorizationContext::class, $context);

        $shared = $this->middleware()->share($request);

        self::assertSame('ms', $shared['locale']);
        self::assertSame('ms', app()->getLocale());
    }

    public function test_a_clinic_owner_who_never_set_a_preference_defaults_to_english(): void
    {
        [$tenantId, $authorityId] = $this->provisionOwner();

        $context = new AuthorizationContext('clinic_owner', $authorityId, $tenantId, 'clinic_owner', 'Dr Aisyah', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard', 'GET');
        $request->attributes->set(AuthorizationContext::class, $context);

        $shared = $this->middleware()->share($request);

        self::assertSame('en', $shared['locale']);
    }

    public function test_a_request_with_no_authorization_context_falls_back_to_the_configured_app_locale(): void
    {
        $request = Request::create('/', 'GET');

        $shared = $this->middleware()->share($request);

        self::assertSame((string) config('app.locale', 'en'), $shared['locale']);
    }

    private function middleware(): HandleInertiaRequests
    {
        return new HandleInertiaRequests(
            new class implements PendingOnboardingJobsReadInterface
            {
                public function countPending(): int
                {
                    return 0;
                }

                public function recentPending(int $limit): array
                {
                    return [];
                }
            },
            new class implements PendingWebsiteDesignerTasksReadInterface
            {
                public function countPendingFor(string $platformIdentityId): int
                {
                    return 0;
                }

                public function recentPendingFor(string $platformIdentityId, int $limit): array
                {
                    return [];
                }
            },
            new class implements ClinicRegistrationReviewReadInterface
            {
                public function list(
                    ?string $status,
                    int $limit = 100,
                    ?string $search = null,
                    ?DateTimeImmutable $registeredFrom = null,
                    ?DateTimeImmutable $registeredBefore = null,
                    string $scope = 'active',
                ): array {
                    return [];
                }
            },
            new class implements ClinicOwnerBookingReadInterface
            {
                public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
                {
                    return null;
                }

                public function list(
                    string $trustedTenantId,
                    ?string $status,
                    ?string $cursor,
                    int $limit,
                    ?string $search = null,
                    ?string $source = null,
                ): array {
                    return [];
                }

                public function countByStatus(string $trustedTenantId): array
                {
                    return [];
                }

                public function countBySource(string $trustedTenantId): array
                {
                    return [];
                }

                public function history(string $trustedTenantId, string $bookingId): array
                {
                    return [];
                }
            },
        );
    }

    /** @return array{0: string, 1: string} tenantId, authorityId */
    private function provisionOwner(): array
    {
        $tenantId = $this->uuid(1);
        $authorityId = $this->uuid(11);
        $clinicOwnerIdentityId = $this->uuid(21);

        $tenant = Tenant::provision(new TenantId($tenantId), $this->time());
        $tenant->establishClinicOwnerAuthority(
            new ClinicOwnerAuthorityId($authorityId),
            new ClinicOwnerIdentity(new ClinicOwnerIdentityId($clinicOwnerIdentityId), new ClinicOwnerEmail('owner@example.test'), new ClinicOwnerName('Dr Aisyah')),
            $this->time(),
        );
        $tenant->activate($this->time());
        $tenant->changeClinicOwnerPasswordHash(new ClinicOwnerAuthorityId($authorityId), Hash::make('Synthetic-Password-123!'));
        $tenant->verifyClinicOwnerEmail(new ClinicOwnerAuthorityId($authorityId), $this->time());
        (new PostgresTenantRepository($this->connection(), new TenantPersistenceMapper))->save($tenant);

        return [$tenantId, $authorityId];
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
