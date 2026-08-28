<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\TenantManagement\Queries;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Queries\PostgresClinicOwnerLocalePreferenceReadAdapter;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresClinicOwnerLocalePreferenceReadAdapterTest extends TestCase
{
    private const string CONNECTION = 'clinic_owner_locale_preference_read_adapter_integration';

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

    public function test_a_tenant_with_no_owner_preference_reads_as_null(): void
    {
        $tenantId = $this->provisionOwner($this->uuid(1), $this->uuid(11), $this->uuid(21));

        self::assertNull((new PostgresClinicOwnerLocalePreferenceReadAdapter($this->connection()))->forTenant($tenantId));
    }

    public function test_an_owners_explicit_preference_is_read_back(): void
    {
        $tenantId = $this->provisionOwner($this->uuid(1), $this->uuid(11), $this->uuid(21));
        $this->connection()->table('clinic_owner_authorities')->where('id', $this->uuid(11))->update(['preferred_locale' => 'ms']);

        self::assertSame('ms', (new PostgresClinicOwnerLocalePreferenceReadAdapter($this->connection()))->forTenant($tenantId));
    }

    public function test_an_unknown_tenant_reads_as_null(): void
    {
        self::assertNull((new PostgresClinicOwnerLocalePreferenceReadAdapter($this->connection()))->forTenant($this->uuid(999)));
    }

    /** @return string tenantId */
    private function provisionOwner(string $tenantId, string $authorityId, string $clinicOwnerIdentityId): string
    {
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
