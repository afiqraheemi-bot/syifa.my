<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Dashboard;

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
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerAccountController;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression test for a production incident (2026-08-24): every real Clinic
 * Owner got a 404 on /dashboard/account. AuthorizationContext::$identityId
 * for a Clinic Owner actor is always the authority row's own primary key
 * (ClinicOwnerSessionState::$authorityId — see
 * CurrentUserResolver::resolve()), never clinic_owner_identity_id (a
 * separate, distinct column — the two are never expected to be equal, as
 * ClinicOwnerGuardIntegrationTest's own fixtures already show: authorityId
 * and clinicOwnerIdentityId are seeded from different UUID suffixes). The
 * controller's owner() lookup queried by clinic_owner_identity_id instead of
 * id, so it never matched a real row and firstOrFail() turned every
 * authenticated visit into a 404.
 */
final class ClinicOwnerAccountControllerTest extends TestCase
{
    private const string CONNECTION = 'clinic_owner_account_controller_integration';

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

    public function test_a_real_clinic_owner_whose_identity_id_differs_from_their_authority_id_can_load_the_account_page(): void
    {
        $tenantId = $this->uuid(1);
        $authorityId = $this->uuid(11);
        $clinicOwnerIdentityId = $this->uuid(21);
        self::assertNotSame($authorityId, $clinicOwnerIdentityId, 'The fixture must mirror production: these are always distinct.');

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

        $context = new AuthorizationContext('clinic_owner', $authorityId, $tenantId, 'clinic_owner', 'Dr Aisyah', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard/account', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', '1');
        $request->attributes->set(AuthorizationContext::class, $context);

        $response = $this->app->make(ClinicOwnerAccountController::class)->show($request);

        $props = $response->toResponse($request)->getData(true)['props'];
        self::assertSame('Dr Aisyah', $props['profile']['name']);
        self::assertSame('owner@example.test', $props['profile']['email']);
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
