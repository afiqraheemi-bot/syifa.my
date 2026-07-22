<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Persistence;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\StaleBookingFormConfigurationWriteException;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingFormConfigurationStorageStateException;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingFormConfigurationRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresBookingFormConfigurationRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'booking_form_configuration_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresBookingFormConfigurationRepository $repository = null;

    private ?Migration $migration = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('BOOKING_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires BOOKING_POSTGRES_TEST_DSN (or SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN) for a dedicated disposable PostgreSQL database.');
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
            'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('booking_form_configurations');

        $migration = require base_path('database/migrations/booking/2026_08_01_000001_create_booking_form_configurations_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migration->up();

        $this->repository = new PostgresBookingFormConfigurationRepository($this->connection, new BookingFormConfigurationPersistenceMapper);
    }

    protected function tearDown(): void
    {
        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_and_reload_a_newly_created_configuration(): void
    {
        $configuration = $this->configuration();
        $this->repository()->save($configuration);

        $reloaded = $this->repository()->findByTenant($configuration->tenantId);

        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version());
        self::assertTrue($reloaded->isEnabled(BookingFormField::Service));
        self::assertFalse($reloaded->isEnabled(BookingFormField::Doctor));
        self::assertTrue($reloaded->isEnabled(BookingFormField::Email));
        self::assertFalse($reloaded->isEnabled(BookingFormField::Branch));
        self::assertTrue($reloaded->isEnabled(BookingFormField::Notes));
        self::assertSame(['patient_name', 'phone'], $reloaded->requiredFields()->values());
        self::assertSame(
            ['patient_name', 'phone', 'appointment_date', 'appointment_time', 'service', 'email', 'notes'],
            $reloaded->fieldOrder()->values(),
        );
        self::assertSame('Additional Notes', $reloaded->fieldLabels()->labelFor(BookingFormField::Notes));
        self::assertSame($configuration->createdAt->format(DATE_ATOM), $reloaded->createdAt->format(DATE_ATOM));
        self::assertSame($configuration->updatedAt()->format(DATE_ATOM), $reloaded->updatedAt()->format(DATE_ATOM));
    }

    public function test_unknown_tenant_resolves_to_null(): void
    {
        self::assertNull($this->repository()->findByTenant(new TenantId($this->uuid(99))));
    }

    public function test_update_persists_a_reordering_and_a_required_fields_change(): void
    {
        $configuration = $this->configuration();
        $this->repository()->save($configuration);

        $configuration->updateFieldOrder(new FieldOrder([
            BookingFormField::Service,
            BookingFormField::Email,
            BookingFormField::Notes,
            BookingFormField::PatientName,
            BookingFormField::Phone,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
        ]), $this->time()->modify('+1 day'));
        $configuration->updateRequiredFields(new RequiredFields([BookingFormField::Service]), $this->time()->modify('+1 day'));
        $this->repository()->save($configuration);

        $reloaded = $this->repository()->findByTenant($configuration->tenantId);

        self::assertNotNull($reloaded);
        self::assertSame(2, $reloaded->version());
        self::assertSame(
            ['service', 'email', 'notes', 'patient_name', 'phone', 'appointment_date', 'appointment_time'],
            $reloaded->fieldOrder()->values(),
        );
        self::assertSame(['service'], $reloaded->requiredFields()->values());
    }

    public function test_persistence_round_trip_after_an_atomic_reconfigure(): void
    {
        $configuration = $this->configuration();
        $this->repository()->save($configuration);

        $configuration->reconfigure(
            true,
            true, // Doctor enabled ...
            true,
            false,
            true,
            new RequiredFields([BookingFormField::Doctor]), // ... required ...
            new FieldOrder([
                BookingFormField::Doctor, // ... ordered first ...
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
            ]),
            new FieldLabels(['doctor' => 'Preferred Doctor']), // ... and labelled, together.
            $this->time()->modify('+1 day'),
        );
        $this->repository()->save($configuration);

        $reloaded = $this->repository()->findByTenant($configuration->tenantId);

        self::assertNotNull($reloaded);
        self::assertSame(2, $reloaded->version());
        self::assertTrue($reloaded->isEnabled(BookingFormField::Doctor));
        self::assertSame(['doctor'], $reloaded->requiredFields()->values());
        self::assertSame('doctor', $reloaded->fieldOrder()->values()[0]);
        self::assertSame('Preferred Doctor', $reloaded->fieldLabels()->labelFor(BookingFormField::Doctor));
    }

    public function test_database_enforces_exactly_one_configuration_per_tenant(): void
    {
        $this->repository()->save($this->configuration());

        $this->expectException(QueryException::class);

        $this->repository()->save($this->configuration());
    }

    public function test_optimistic_locking_rejects_a_stale_write(): void
    {
        $configuration = $this->configuration();
        $this->repository()->save($configuration);

        $firstCopy = $this->repository()->findByTenant($configuration->tenantId);
        $staleCopy = $this->repository()->findByTenant($configuration->tenantId);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->updateRequiredFields(new RequiredFields([BookingFormField::Service]), $this->time());
        $this->repository()->save($firstCopy);

        $staleCopy->updateRequiredFields(new RequiredFields([BookingFormField::Email]), $this->time());

        $this->expectException(StaleBookingFormConfigurationWriteException::class);

        $this->repository()->save($staleCopy);
    }

    public function test_optimistic_locking_remains_correct_for_atomic_reconfigure(): void
    {
        $configuration = $this->configuration();
        $this->repository()->save($configuration);

        $firstCopy = $this->repository()->findByTenant($configuration->tenantId);
        $staleCopy = $this->repository()->findByTenant($configuration->tenantId);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->reconfigure(
            true,
            true,
            true,
            false,
            true,
            new RequiredFields([BookingFormField::Doctor]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
                BookingFormField::Doctor,
            ]),
            $firstCopy->fieldLabels(),
            $this->time(),
        );
        $this->repository()->save($firstCopy);

        $staleCopy->reconfigure(
            true,
            false,
            true,
            true, // Branch enabled instead ...
            true,
            $staleCopy->requiredFields(),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
                BookingFormField::Branch, // ... and ordered, together.
            ]),
            $staleCopy->fieldLabels(),
            $this->time(),
        );

        $this->expectException(StaleBookingFormConfigurationWriteException::class);

        $this->repository()->save($staleCopy);
    }

    public function test_reconstitution_rejects_a_corrupted_stored_configuration(): void
    {
        $this->connection()->table('booking_form_configurations')->insert([
            'tenant_id' => $this->uuid(1),
            'enable_service_selection' => false,
            'enable_doctor_selection' => false,
            'enable_email' => false,
            'enable_branch' => false,
            'enable_notes' => false,
            'required_fields' => json_encode(['email'], JSON_THROW_ON_ERROR), // required while disabled — corrupted
            'field_order' => json_encode(['patient_name', 'phone', 'appointment_date', 'appointment_time'], JSON_THROW_ON_ERROR),
            'field_labels' => json_encode([], JSON_THROW_ON_ERROR),
            'domain_created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'domain_updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'version' => 1,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);

        $this->expectException(InvalidBookingFormConfigurationStorageStateException::class);

        $this->repository()->findByTenant(new TenantId($this->uuid(1)));
    }

    private function configuration(): BookingFormConfiguration
    {
        return BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true,
            false,
            true,
            false,
            true,
            new RequiredFields([BookingFormField::PatientName, BookingFormField::Phone]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
            ]),
            new FieldLabels(['notes' => 'Additional Notes']),
            $this->time(),
        );
    }

    private function repository(): PostgresBookingFormConfigurationRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-01T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
