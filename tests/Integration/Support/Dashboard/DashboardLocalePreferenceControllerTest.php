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
use App\Support\Dashboard\Presentation\Http\Controllers\DashboardLocalePreferenceController;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class DashboardLocalePreferenceControllerTest extends TestCase
{
    private const string CONNECTION = 'dashboard_locale_preference_controller_integration';

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
        parent::tearDown();
    }

    public function test_a_clinic_owner_can_switch_their_preferred_locale_to_malay(): void
    {
        [$tenantId, $authorityId] = $this->provisionOwner();
        $context = new AuthorizationContext('clinic_owner', $authorityId, $tenantId, 'clinic_owner', 'Dr Aisyah', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard/preferences/locale', 'PATCH', ['locale' => 'ms']);
        $request->attributes->set(AuthorizationContext::class, $context);

        $this->app->make(DashboardLocalePreferenceController::class)->update($request);

        self::assertSame(
            'ms',
            $this->connection()->table('clinic_owner_authorities')->where('id', $authorityId)->value('preferred_locale'),
        );
    }

    public function test_an_unsupported_locale_value_is_rejected(): void
    {
        [$tenantId, $authorityId] = $this->provisionOwner();
        $context = new AuthorizationContext('clinic_owner', $authorityId, $tenantId, 'clinic_owner', 'Dr Aisyah', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard/preferences/locale', 'PATCH', ['locale' => 'fr']);
        $request->attributes->set(AuthorizationContext::class, $context);

        $this->expectException(ValidationException::class);

        $this->app->make(DashboardLocalePreferenceController::class)->update($request);
    }

    public function test_a_request_without_an_authorization_context_is_forbidden(): void
    {
        $request = Request::create('/dashboard/preferences/locale', 'PATCH', ['locale' => 'ms']);

        try {
            $this->app->make(DashboardLocalePreferenceController::class)->update($request);
            self::fail('Expected an HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
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
