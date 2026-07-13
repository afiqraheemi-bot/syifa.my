<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\TenantManagement\Persistence;

use App\Modules\TenantManagement\Application\Authentication\ClinicOwnerPasswordMatcher;
use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\StaleTenantWriteException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantLifecycleStatus;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantReactivationReadiness;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\AdminHostParser;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

final class PostgresTenantRepositoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    private ?PostgresTenantRepository $repository = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('TENANT_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped(
                'Requires TENANT_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.',
            );
        }

        config()->set('database.default', 'tenant_postgres_integration');
        config()->set('database.connections.tenant_postgres_integration', [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('tenant_postgres_integration');
        $this->connection = DB::connection('tenant_postgres_integration');
        Schema::dropIfExists('clinic_owner_authorities');
        Schema::dropIfExists('tenants');
        $migration = require base_path(
            'database/migrations/tenant_management/2026_07_13_000001_create_tenant_aggregate_tables.php',
        );
        self::assertInstanceOf(Migration::class, $migration);
        $this->migrations[] = $migration;
        $migration->up();
        $credentialMigration = require base_path(
            'database/migrations/tenant_management/2026_07_13_000002_add_clinic_owner_credential_state.php',
        );
        self::assertInstanceOf(Migration::class, $credentialMigration);
        $this->migrations[] = $credentialMigration;
        $credentialMigration->up();
        $routingLabelMigration = require base_path(
            'database/migrations/tenant_management/2026_07_13_000003_add_tenant_admin_routing_label.php',
        );
        self::assertInstanceOf(Migration::class, $routingLabelMigration);
        $this->migrations[] = $routingLabelMigration;
        $routingLabelMigration->up();
        $this->repository = new PostgresTenantRepository(
            $this->connection,
            new TenantPersistenceMapper,
        );
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge('tenant_postgres_integration');
        parent::tearDown();
    }

    public function test_it_persists_and_reloads_a_provisioned_tenant(): void
    {
        $tenant = $this->tenant();
        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);

        self::assertNotNull($reloaded);
        self::assertSame(TenantLifecycleStatus::Provisioning, $reloaded->status());
        self::assertSame(1, $reloaded->version());
        self::assertNull($reloaded->adminRoutingLabel());
    }

    public function test_admin_routing_label_persists_and_reloads(): void
    {
        $tenant = $this->tenant();
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel('clinic-one'));
        $this->repository()->save($tenant);

        self::assertSame(
            'clinic-one',
            $this->repository()->find($tenant->id)?->adminRoutingLabel()?->value,
        );
    }

    public function test_admin_routing_label_is_platform_wide_unique_while_null_remains_allowed(): void
    {
        $legacyTenant = $this->tenant(1);
        $first = $this->tenant(2);
        $second = $this->tenant(3);
        $first->assignAdminRoutingLabel(new TenantAdminRoutingLabel('reserved-clinic'));
        $second->assignAdminRoutingLabel(new TenantAdminRoutingLabel('reserved-clinic'));
        $this->repository()->save($legacyTenant);
        $this->repository()->save($first);

        self::assertNull($this->repository()->find($legacyTenant->id)?->adminRoutingLabel());

        $this->expectException(QueryException::class);
        $this->repository()->save($second);
    }

    public function test_database_rejects_an_invalid_admin_routing_label_structure(): void
    {
        $tenant = $this->tenant();
        $this->repository()->save($tenant);

        $this->expectException(QueryException::class);
        $this->connection()->table('tenants')
            ->where('id', $tenant->id->value)
            ->update(['admin_routing_label' => 'Invalid.Host']);
    }

    public function test_suspended_and_anonymized_tenant_labels_remain_reserved_and_do_not_resolve(): void
    {
        $tenant = $this->activeTenant(1, 'clinic-one');
        $this->repository()->save($tenant);
        $tenant->suspend($this->time('10:03:00'));
        $this->repository()->save($tenant);
        $lookup = new PostgresTenantAdminRoutingLookup($this->connection());

        self::assertNull($lookup->resolve('clinic-one'));
        self::assertSame(
            'clinic-one',
            $this->repository()->find($tenant->id)?->adminRoutingLabel()?->value,
        );

        $tenant->beginOffboarding($this->time('10:04:00'));
        $tenant->anonymize($this->time('10:05:00'));
        $this->repository()->save($tenant);

        self::assertNull($lookup->resolve('clinic-one'));
        self::assertSame(
            'clinic-one',
            $this->repository()->find($tenant->id)?->adminRoutingLabel()?->value,
        );

        $replacement = $this->tenant(2);
        $replacement->assignAdminRoutingLabel(new TenantAdminRoutingLabel('clinic-one'));

        $this->expectException(QueryException::class);
        $this->repository()->save($replacement);
    }

    public function test_lookup_resolves_only_eligible_tenant_with_minimum_data(): void
    {
        $tenant = $this->activeTenant(1, 'clinic-one');
        $this->repository()->save($tenant);
        $lookup = new PostgresTenantAdminRoutingLookup($this->connection());
        $result = $lookup->resolve('clinic-one');

        self::assertNotNull($result);
        self::assertSame($tenant->id->value, $result->tenantId);
        self::assertSame('clinic-one', $result->routingLabel);
        self::assertSame('active', $result->lifecycleStatus);
        self::assertSame(
            ['tenantId', 'routingLabel', 'lifecycleStatus'],
            array_keys(get_object_vars($result)),
        );
        self::assertNull($lookup->resolve('unknown-clinic'));
        self::assertNull($lookup->resolve('Invalid.Host'));

        $provisioning = $this->tenant(2);
        $provisioning->assignAdminRoutingLabel(new TenantAdminRoutingLabel('pending-clinic'));
        $this->repository()->save($provisioning);
        self::assertNull($lookup->resolve('pending-clinic'));

        $tenant->suspend($this->time('10:03:00'));
        $this->repository()->save($tenant);
        $tenant->reactivate(
            new TenantReactivationReadiness(true, true, true, true, true),
            $this->time('10:04:00'),
        );
        $this->repository()->save($tenant);
        self::assertSame('reactivated', $lookup->resolve('clinic-one')?->lifecycleStatus);
    }

    public function test_trusted_selector_resolves_an_eligible_tenant_through_the_real_lookup(): void
    {
        $tenant = $this->activeTenant(1, 'clinic-one');
        $this->repository()->save($tenant);
        $selector = new TenantAdminHostTrustedTenantSelector(
            new AdminHostParser(['app.syifa.my']),
            new PostgresTenantAdminRoutingLookup($this->connection()),
        );

        self::assertSame(
            $tenant->id->value,
            $selector->select('clinic-one.app.syifa.my')?->tenantId,
        );
        self::assertNull($selector->select('unknown-clinic.app.syifa.my'));
        self::assertNull($selector->select('clinic-one.syifa.my'));
    }

    public function test_clinic_owner_context_revalidates_persisted_authority_on_every_resolution(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->activate($this->time('10:02:00'));
        $this->repository()->save($tenant);
        $resolver = new ClinicOwnerTenantContextResolver($this->repository());
        $resolution = new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $tenant->id->value,
            role: 'clinic_owner',
            assignment: null,
            clinicOwnerAuthorityId: $this->uuid(10),
            clinicOwnerIdentityId: $this->uuid(20),
        );

        self::assertSame($tenant->id->value, $resolver->resolve($resolution)?->tenantId);

        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:03:00'));
        $this->repository()->save($tenant);

        self::assertNull($resolver->resolve($resolution));
    }

    public function test_it_persists_and_reloads_an_activated_tenant(): void
    {
        $tenant = $this->tenantWithAuthority();
        $tenant->activate($this->time('10:02:00'));
        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);

        self::assertNotNull($reloaded);
        self::assertSame(TenantLifecycleStatus::Active, $reloaded->status());
        self::assertNotNull($reloaded->activeClinicOwnerAuthority());
    }

    public function test_authority_transfer_and_complete_history_survive_reconstitution(): void
    {
        $tenant = $this->tenantWithAuthority();
        $this->repository()->save($tenant);
        $tenant->transferClinicOwnerAuthority(
            $this->authorityId(10),
            $this->authorityId(11),
            $this->identity(21, 'replacement@example.test'),
            $this->time('10:02:00'),
        );
        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);

        self::assertNotNull($reloaded);
        self::assertCount(2, $reloaded->clinicOwnerAuthorityHistory());
        self::assertFalse($reloaded->clinicOwnerAuthorityHistory()[0]->isActive());
        self::assertSame($this->uuid(11), $reloaded->activeClinicOwnerAuthority()?->id->value);
    }

    public function test_revoked_authority_remains_revoked_after_reload(): void
    {
        $tenant = $this->tenantWithAuthority();
        $this->repository()->save($tenant);
        $tenant->revokeClinicOwnerAuthority($this->authorityId(10), $this->time('10:02:00'));
        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->activeClinicOwnerAuthority());
        self::assertFalse($reloaded->clinicOwnerAuthorityHistory()[0]->isActive());
    }

    public function test_stale_aggregate_version_write_is_rejected(): void
    {
        $tenant = $this->tenantWithAuthority();
        $this->repository()->save($tenant);
        $firstCopy = $this->repository()->find($tenant->id);
        $staleCopy = $this->repository()->find($tenant->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);
        $firstCopy->activate($this->time('10:02:00'));
        $this->repository()->save($firstCopy);
        $staleCopy->activate($this->time('10:03:00'));

        $this->expectException(StaleTenantWriteException::class);
        $this->repository()->save($staleCopy);
    }

    public function test_tenant_and_authority_changes_are_atomic(): void
    {
        $connection = $this->connection();
        $connection->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_authority() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'forced authority failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_authority_trigger
            BEFORE INSERT ON clinic_owner_authorities
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_authority();
            SQL);
        $tenant = $this->tenantWithAuthority();

        try {
            $this->repository()->save($tenant);
            self::fail('The forced authority failure should abort the aggregate transaction.');
        } catch (Throwable) {
            self::assertFalse($connection->table('tenants')->where('id', $tenant->id->value)->exists());
            self::assertSame(0, $tenant->version());
        } finally {
            $connection->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_authority_trigger ON clinic_owner_authorities');
            $connection->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_authority()');
        }
    }

    public function test_credential_verification_email_and_lockout_state_survive_reconstitution(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $tenant = $this->tenantWithAuthority();
        $tenant->changeClinicOwnerPasswordHash($this->authorityId(10), $hasher->make('Synthetic-Password-123'));
        $tenant->verifyClinicOwnerEmail($this->authorityId(10), $this->time('10:02:00'));
        $tenant->activate($this->time('10:03:00'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $tenant->verifyClinicOwnerCredential(
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerPasswordMatcher($hasher, 'Incorrect-Password-123'),
                $this->time(sprintf('10:%02d:00', 4 + $attempt)),
            );
        }

        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);
        self::assertNotNull($reloaded);
        $state = $reloaded->activeClinicOwnerAuthority()?->credentialState();
        self::assertNotNull($state);
        self::assertTrue($state->isEmailVerified());
        self::assertSame(5, $state->failedAttemptCount);
        self::assertNotNull($state->lockoutUntil);
        self::assertSame(1, $state->credentialVersion);
        self::assertTrue($state->matches(new ClinicOwnerPasswordMatcher($hasher, 'Synthetic-Password-123')));
    }

    public function test_application_verification_uses_tenant_repository_and_persists_failure_state(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $tenant = $this->tenantWithAuthority();
        $tenant->changeClinicOwnerPasswordHash($this->authorityId(10), $hasher->make('Synthetic-Password-123'));
        $tenant->activate($this->time('10:02:00'));
        $this->repository()->save($tenant);
        $service = new VerifyClinicOwnerCredentialService($this->repository(), $hasher);

        self::assertFalse($service->verify(
            $tenant->id->value,
            'owner@example.test',
            'Incorrect-Password-123',
            $this->time('10:03:00'),
        )->verified);
        $reloaded = $this->repository()->find($tenant->id);
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->activeClinicOwnerAuthority()?->credentialState()->failedAttemptCount);
    }

    public function test_same_email_in_two_tenants_never_causes_implicit_tenant_selection(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $first = $this->tenantWithAuthority();
        $first->changeClinicOwnerPasswordHash($this->authorityId(10), $hasher->make('First-Password-123'));
        $first->activate($this->time('10:02:00'));
        $this->repository()->save($first);

        $second = Tenant::provision(new TenantId($this->uuid(2)), $this->time('10:00:00'));
        $second->establishClinicOwnerAuthority(
            $this->authorityId(11),
            $this->identity(21, 'owner@example.test'),
            $this->time('10:01:00'),
        );
        $second->changeClinicOwnerPasswordHash($this->authorityId(11), $hasher->make('Second-Password-123'));
        $second->activate($this->time('10:02:00'));
        $this->repository()->save($second);
        $service = new VerifyClinicOwnerCredentialService($this->repository(), $hasher);

        self::assertFalse($service->verify(
            $second->id->value,
            'owner@example.test',
            'First-Password-123',
            $this->time('10:03:00'),
        )->verified);
        self::assertTrue($service->verify(
            $first->id->value,
            'owner@example.test',
            'First-Password-123',
            $this->time('10:03:00'),
        )->verified);
    }

    public function test_credential_and_tenant_changes_are_atomic(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $tenant = $this->tenantWithAuthority();
        $tenant->changeClinicOwnerPasswordHash($this->authorityId(10), $hasher->make('Synthetic-Password-123'));
        $tenant->activate($this->time('10:02:00'));
        $this->repository()->save($tenant);
        $reloaded = $this->repository()->find($tenant->id);
        self::assertNotNull($reloaded);
        $reloaded->changeClinicOwnerPasswordHash(
            $this->authorityId(10),
            $hasher->make('Replacement-Password-456'),
        );
        $reloaded->suspend($this->time('10:03:00'));
        $connection = $this->connection();
        $connection->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_credential_update() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'forced credential failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_credential_update_trigger
            BEFORE UPDATE ON clinic_owner_authorities
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_credential_update();
            SQL);

        try {
            $this->repository()->save($reloaded);
            self::fail('The forced credential failure should abort the Tenant transaction.');
        } catch (Throwable) {
            $persisted = $this->repository()->find($tenant->id);
            self::assertNotNull($persisted);
            self::assertSame(TenantLifecycleStatus::Active, $persisted->status());
            self::assertSame(1, $persisted->activeClinicOwnerAuthority()?->credentialState()->credentialVersion);
        } finally {
            $connection->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_credential_update_trigger ON clinic_owner_authorities');
            $connection->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_credential_update()');
        }
    }

    private function activeTenant(int $suffix, string $routingLabel): Tenant
    {
        $tenant = $this->tenant($suffix);
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel($routingLabel));
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(10 + $suffix),
            $this->identity(20 + $suffix, sprintf('owner%d@example.test', $suffix)),
            $this->time('10:01:00'),
        );
        $tenant->activate($this->time('10:02:00'));

        return $tenant;
    }

    private function tenantWithAuthority(): Tenant
    {
        $tenant = $this->tenant();
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(10),
            $this->identity(20, 'owner@example.test'),
            $this->time('10:01:00'),
        );

        return $tenant;
    }

    private function tenant(int $suffix = 1): Tenant
    {
        return Tenant::provision(new TenantId($this->uuid($suffix)), $this->time('10:00:00'));
    }

    private function authorityId(int $suffix): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid($suffix));
    }

    private function identity(int $suffix, string $email): ClinicOwnerIdentity
    {
        return new ClinicOwnerIdentity(
            new ClinicOwnerIdentityId($this->uuid($suffix)),
            new ClinicOwnerEmail($email),
            new ClinicOwnerName('Clinic Owner'),
        );
    }

    private function repository(): PostgresTenantRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
