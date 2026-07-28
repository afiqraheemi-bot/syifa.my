<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\TenantManagement\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerUserProvider;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\TenantManagement\Infrastructure\Session\LaravelClinicOwnerSessionStore;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sprint 3A Phase 1: proves the real, container-wired `clinic_owner` Guard —
 * not a Unit-level fake — genuinely reflects state against a real Postgres
 * row once `LaravelClinicOwnerSessionStore::establish()` runs, and that
 * `invalidate()` genuinely clears it. `retrieveById` (the path a *following*
 * HTTP request takes to rehydrate the session) is exercised directly here.
 * Normal session establishment does not query the database; remember-me
 * establishment deliberately resolves the already-verified authority row so
 * Laravel can persist a genuine recaller token.
 *
 * Row-level isolation, following the established pattern also used by
 * `PostgresTenantRepositoryTest`: this test owns its own schema lifecycle
 * (create in setUp, drop in tearDown) rather than depending on the shared
 * disposable database's migration ledger.
 */
final class ClinicOwnerGuardIntegrationTest extends TestCase
{
    private const string CONNECTION = 'clinic_owner_guard_integration';

    private const string PASSWORD = 'Synthetic-Password-123!';

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
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
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
        ] as $file) {
            $migration = require base_path('database/migrations/tenant_management/'.$file);
            self::assertInstanceOf(Migration::class, $migration);
            $this->migrations[] = $migration;
            $migration->up();
        }

        config()->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_establish_logs_in_the_native_guard_and_invalidate_logs_it_out(): void
    {
        $tenantId = $this->seedActiveClinicOwner(1, self::PASSWORD);

        $session = $this->app->make(Session::class);
        $store = new LaravelClinicOwnerSessionStore($session, $this->app->make(AuthFactory::class));

        $guard = $this->guard();
        self::assertFalse($guard->check());

        $store->establish(new ClinicOwnerSessionState(
            tenantId: $tenantId,
            authorityId: $this->uuid(11),
            clinicOwnerIdentityId: $this->uuid(21),
            role: 'clinic_owner',
            authenticatedAt: new DateTimeImmutable,
            lastActivityAt: new DateTimeImmutable,
            absoluteExpiresAt: (new DateTimeImmutable)->modify('+12 hours'),
        ));

        self::assertTrue($guard->check());
        self::assertSame($this->uuid(11), $guard->id());

        $store->invalidate();
        self::assertFalse($guard->check());
    }

    public function test_user_provider_retrieve_by_id_resolves_the_real_row_for_session_reload(): void
    {
        $tenantId = $this->seedActiveClinicOwner(2, self::PASSWORD);
        $authorityId = $this->uuid(12);

        $resolved = $this->userProvider()->retrieveById($authorityId);

        self::assertNotNull($resolved);
        self::assertSame($authorityId, $resolved->getAuthIdentifier());
        self::assertSame($tenantId, $resolved->tenant_id);
        self::assertSame('owner2@example.test', $resolved->email);
    }

    public function test_user_provider_retrieve_by_id_returns_null_for_an_unknown_authority(): void
    {
        self::assertNull($this->userProvider()->retrieveById($this->uuid(999)));
    }

    public function test_remembered_establishment_persists_a_recaller_token_for_the_verified_tenant_authority(): void
    {
        $tenantId = $this->seedActiveClinicOwner(3, self::PASSWORD);
        $authorityId = $this->uuid(13);
        $store = new LaravelClinicOwnerSessionStore(
            $this->app->make(Session::class),
            $this->app->make(AuthFactory::class),
        );

        $store->establish(new ClinicOwnerSessionState(
            tenantId: $tenantId,
            authorityId: $authorityId,
            clinicOwnerIdentityId: $this->uuid(23),
            role: 'clinic_owner',
            authenticatedAt: new DateTimeImmutable,
            lastActivityAt: new DateTimeImmutable,
            absoluteExpiresAt: (new DateTimeImmutable)->modify('+12 hours'),
        ), true);

        $rememberToken = $this->connection
            ?->table('clinic_owner_authorities')
            ->where('id', $authorityId)
            ->where('tenant_id', $tenantId)
            ->value('remember_token');

        self::assertIsString($rememberToken);
        self::assertNotSame('', $rememberToken);
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->app->make(AuthFactory::class)->guard('clinic_owner');
        self::assertInstanceOf(StatefulGuard::class, $guard);

        return $guard;
    }

    private function userProvider(): ClinicOwnerUserProvider
    {
        return new ClinicOwnerUserProvider($this->app->make(ClinicOwnerCredentialVerificationInterface::class));
    }

    private function seedActiveClinicOwner(int $suffix, string $password): string
    {
        $tenant = Tenant::provision(new TenantId($this->uuid($suffix)), $this->time());
        $tenant->establishClinicOwnerAuthority(
            new ClinicOwnerAuthorityId($this->uuid(10 + $suffix)),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId($this->uuid(20 + $suffix)),
                new ClinicOwnerEmail(sprintf('owner%d@example.test', $suffix)),
                new ClinicOwnerName('Clinic Owner'),
            ),
            $this->time(),
        );
        $tenant->activate($this->time());
        $tenant->changeClinicOwnerPasswordHash(new ClinicOwnerAuthorityId($this->uuid(10 + $suffix)), Hash::make($password));
        $tenant->verifyClinicOwnerEmail(new ClinicOwnerAuthorityId($this->uuid(10 + $suffix)), $this->time());

        (new PostgresTenantRepository($this->connection, new TenantPersistenceMapper))->save($tenant);

        return $tenant->id->value;
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
