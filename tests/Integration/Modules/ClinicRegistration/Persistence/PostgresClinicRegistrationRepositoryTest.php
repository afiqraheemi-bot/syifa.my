<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\ClinicRegistration\Persistence;

use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\Exceptions\StaleClinicRegistrationWriteException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\TenantId;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Mappers\ClinicRegistrationPersistenceMapper;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Queries\PostgresClinicRegistrationQueryAdapter;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Repositories\PostgresClinicRegistrationRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresClinicRegistrationRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'clinic_registration_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresClinicRegistrationRepository $repository = null;

    private ?PostgresClinicRegistrationQueryAdapter $query = null;

    private ?Migration $migration = null;

    private ?Migration $tenantIdMigration = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('CLINIC_REGISTRATION_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires CLINIC_REGISTRATION_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION_NAME);
        config()->set('database.connections.'.self::CONNECTION_NAME, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);
        Schema::dropIfExists('clinic_registration_declaration_acceptances');
        Schema::dropIfExists('clinic_registrations');

        $migration = require base_path(
            'database/migrations/clinic_registration/2026_07_20_000001_create_clinic_registration_tables.php',
        );
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migration->up();

        $tenantIdMigration = require base_path(
            'database/migrations/clinic_registration/2026_07_26_000001_add_reserved_tenant_id_to_clinic_registrations.php',
        );
        self::assertInstanceOf(Migration::class, $tenantIdMigration);
        $this->tenantIdMigration = $tenantIdMigration;
        $this->tenantIdMigration->up();

        $mapper = new ClinicRegistrationPersistenceMapper;
        $this->repository = new PostgresClinicRegistrationRepository($this->connection, $mapper);
        $this->query = new PostgresClinicRegistrationQueryAdapter($this->connection);
    }

    protected function tearDown(): void
    {
        if ($this->tenantIdMigration !== null) {
            $this->tenantIdMigration->down();
        }

        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_reload_and_query_current_registration(): void
    {
        $registration = $this->submittableRegistration();
        $this->repository()->save($registration);

        $reloaded = $this->repository()->find($registration->id);
        $current = $this->query()->currentForPlatformIdentity($registration->platformIdentity->value);

        self::assertNotNull($reloaded);
        self::assertNotNull($current);
        self::assertSame(1, $reloaded->version());
        self::assertSame('draft', $current->status);
        self::assertCount(1, $current->declarations);
    }

    public function test_submission_and_provisioning_survive_reload(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->time());
        $this->repository()->save($registration);

        $reloaded = $this->repository()->findByCorrelationReference($registration->correlationReference);
        self::assertNotNull($reloaded);
        self::assertSame($this->tenantId()->value, $reloaded->reservedTenantId?->value);
        $reloaded->markProvisioned(null, $this->time());
        $this->repository()->save($reloaded);

        $provisioned = $this->repository()->find($registration->id);
        self::assertNotNull($provisioned);
        self::assertSame('provisioned', $provisioned->status->value);
        self::assertSame(2, $provisioned->version());
        self::assertSame($this->tenantId()->value, $provisioned->reservedTenantId?->value);
    }

    public function test_newly_submitted_registration_persists_a_non_null_tenant_id(): void
    {
        $registration = $this->submittableRegistration();
        $registration->submit($this->tenantId(), $this->time());
        $this->repository()->save($registration);

        $row = $this->connection()->table('clinic_registrations')->where('id', $registration->id->value)->first();

        self::assertNotNull($row);
        self::assertSame($this->tenantId()->value, $row->reserved_tenant_id);
    }

    public function test_reserved_tenant_id_column_is_nullable_for_draft_rows(): void
    {
        $registration = $this->submittableRegistration();
        $this->repository()->save($registration);

        $row = $this->connection()->table('clinic_registrations')->where('id', $registration->id->value)->first();

        self::assertNotNull($row);
        self::assertNull($row->reserved_tenant_id);

        $reloaded = $this->repository()->find($registration->id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->reservedTenantId);
    }

    public function test_legacy_null_tenant_id_rows_are_not_automatically_backfilled(): void
    {
        $legacyId = $this->uuid(50);
        $this->connection()->table('clinic_registrations')->insert([
            'id' => $legacyId,
            'platform_identity_id' => $this->uuid(51),
            'status' => 'submitted',
            'registration_correlation_reference' => $legacyId,
            'reserved_tenant_id' => null,
            'submitted_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'version' => 1,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);

        $other = $this->submittableRegistration();
        $other->submit($this->tenantId(), $this->time());
        $this->repository()->save($other);

        $legacyRow = $this->connection()->table('clinic_registrations')->where('id', $legacyId)->first();
        self::assertNotNull($legacyRow);
        self::assertNull($legacyRow->reserved_tenant_id);

        $legacy = $this->repository()->find(new RegistrationId($legacyId));
        self::assertNotNull($legacy);
        self::assertNull($legacy->reservedTenantId);
    }

    public function test_duplicate_active_registration_is_rejected_by_database(): void
    {
        $registration = $this->submittableRegistration();
        $this->repository()->save($registration);

        $this->expectException(QueryException::class);

        $this->connection()->table('clinic_registrations')->insert([
            'id' => $this->uuid(99),
            'platform_identity_id' => $registration->platformIdentity->value,
            'status' => 'draft',
            'registration_correlation_reference' => $this->uuid(99),
            'version' => 1,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);
    }

    public function test_optimistic_locking_rejects_stale_write(): void
    {
        $registration = $this->submittableRegistration();
        $this->repository()->save($registration);
        $firstCopy = $this->repository()->find($registration->id);
        $staleCopy = $this->repository()->find($registration->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->submit($this->tenantId(), $this->time());
        $this->repository()->save($firstCopy);
        $staleCopy->submit($this->tenantId(), $this->time());

        $this->expectException(StaleClinicRegistrationWriteException::class);

        $this->repository()->save($staleCopy);
    }

    private function submittableRegistration(): ClinicRegistration
    {
        $registration = ClinicRegistration::start(
            new RegistrationId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            $this->time(),
        );
        $registration->updateDraft(
            new ClinicRegistrationProfile('Klinik Syifa', 'owner@clinic.test', '+60123456789', '1 Jalan Klinik'),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->time())],
            new CommercialSelectionReference('offering-basic-monthly', 'monthly', 'catalogue-v1'),
        );

        return $registration;
    }

    private function repository(): PostgresClinicRegistrationRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function query(): PostgresClinicRegistrationQueryAdapter
    {
        self::assertNotNull($this->query);

        return $this->query;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-20T00:00:00Z');
    }

    private function tenantId(): TenantId
    {
        return new TenantId($this->uuid(4));
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
