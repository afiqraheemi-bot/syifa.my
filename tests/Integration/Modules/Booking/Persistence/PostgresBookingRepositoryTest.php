<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Persistence;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\Exceptions\StaleBookingWriteException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresBookingRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'booking_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresBookingRepository $repository = null;

    /** @var list<Migration> */
    private array $migrations = [];

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
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('bookings');

        $migration = require base_path('database/migrations/booking/2026_07_30_000001_create_bookings_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();

        $serviceMigration = require base_path('database/migrations/booking/2026_08_02_000001_add_service_id_to_bookings_table.php');
        self::assertInstanceOf(Migration::class, $serviceMigration);
        $serviceMigration->up();
        $clinicLineageMigration = require base_path('database/migrations/booking/2026_08_03_000001_remove_clinic_id_from_bookings_table.php');
        self::assertInstanceOf(Migration::class, $clinicLineageMigration);
        $clinicLineageMigration->up();
        $this->migrations = [$clinicLineageMigration, $serviceMigration, $migration];

        $this->repository = new PostgresBookingRepository($this->connection, new BookingPersistenceMapper);
    }

    protected function tearDown(): void
    {
        foreach ($this->migrations as $migration) {
            $migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_and_reload_a_newly_submitted_booking(): void
    {
        $booking = $this->booking();
        $this->repository()->save($booking);

        $reloaded = $this->repository()->findById($booking->tenantId, $booking->id);

        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version());
        self::assertSame($booking->tenantId->value, $reloaded->tenantId->value);
        self::assertSame($booking->serviceId?->value, $reloaded->serviceId?->value);
        self::assertSame($booking->reference->value, $reloaded->reference->value);
        self::assertSame('submitted', $reloaded->status()->value);
        self::assertSame($booking->patientName->value, $reloaded->patientName->value);
        self::assertSame($booking->patientPhone->value, $reloaded->patientPhone->value);
        self::assertSame($booking->patientEmail?->value, $reloaded->patientEmail?->value);
        self::assertSame($booking->appointmentDate->value, $reloaded->appointmentDate->value);
        self::assertSame($booking->appointmentTime->value, $reloaded->appointmentTime->value);
        self::assertSame($booking->notes, $reloaded->notes);
        self::assertSame($booking->createdAt->format(DATE_ATOM), $reloaded->createdAt->format(DATE_ATOM));
        self::assertSame($booking->updatedAt()->format(DATE_ATOM), $reloaded->updatedAt()->format(DATE_ATOM));
    }

    public function test_find_by_reference_locates_the_same_booking(): void
    {
        $booking = $this->booking();
        $this->repository()->save($booking);

        $found = $this->repository()->findByReference($booking->tenantId, $booking->reference->value);

        self::assertNotNull($found);
        self::assertSame($booking->id->value, $found->id->value);
    }

    public function test_id_and_reference_lookups_do_not_cross_the_tenant_boundary(): void
    {
        $booking = $this->booking();
        $this->repository()->save($booking);
        $otherTenant = new TenantId($this->uuid(9));

        self::assertNull($this->repository()->findById($otherTenant, $booking->id));
        self::assertNull($this->repository()->findByReference($otherTenant, $booking->reference->value));
    }

    public function test_unknown_id_and_reference_resolve_to_null(): void
    {
        $tenantId = new TenantId($this->uuid(2));
        self::assertNull($this->repository()->findById($tenantId, new BookingId($this->uuid(99))));
        self::assertNull($this->repository()->findByReference($tenantId, 'UNKNOWN-REF'));
    }

    public function test_booking_without_email_or_notes_round_trips_as_null(): void
    {
        $booking = $this->booking(patientEmail: null, notes: null, serviceId: null);
        $this->repository()->save($booking);

        $reloaded = $this->repository()->findById($booking->tenantId, $booking->id);

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->patientEmail);
        self::assertNull($reloaded->notes);
        self::assertNull($reloaded->serviceId);
    }

    public function test_additive_migration_keeps_an_existing_booking_compatible(): void
    {
        $this->migrations[1]->down();
        $now = $this->time()->format('Y-m-d H:i:s.uP');
        $this->connection()->table('bookings')->insert([
            'id' => $this->uuid(1),
            'tenant_id' => $this->uuid(2),
            'booking_reference' => 'BOOK-0001',
            'status' => 'submitted',
            'patient_name' => 'Aisyah Rahman',
            'patient_phone' => '+60123456789',
            'patient_email' => null,
            'appointment_on' => '2026-08-01',
            'appointment_time' => '09:30',
            'notes' => null,
            'domain_created_at' => $now,
            'domain_updated_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->migrations[1]->up();

        $reloaded = $this->repository()->findById(new TenantId($this->uuid(2)), new BookingId($this->uuid(1)));
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->serviceId);
    }

    public function test_clinic_lineage_migration_removes_and_reversibly_restores_the_column(): void
    {
        self::assertFalse(Schema::connection(self::CONNECTION_NAME)->hasColumn('bookings', 'clinic_id'));

        $this->migrations[0]->down();
        self::assertTrue(Schema::connection(self::CONNECTION_NAME)->hasColumn('bookings', 'clinic_id'));

        $this->migrations[0]->up();
        self::assertFalse(Schema::connection(self::CONNECTION_NAME)->hasColumn('bookings', 'clinic_id'));
    }

    public function test_database_rejects_a_duplicate_booking_reference(): void
    {
        $this->repository()->save($this->booking());

        $this->expectException(QueryException::class);

        $this->repository()->save($this->booking(id: 9, reference: $this->booking()->reference->value));
    }

    public function test_optimistic_locking_rejects_a_stale_write(): void
    {
        $booking = $this->booking();
        $this->repository()->save($booking);

        $firstCopy = $this->repository()->findById($booking->tenantId, $booking->id);
        $staleCopy = $this->repository()->findById($booking->tenantId, $booking->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $this->repository()->save($firstCopy);

        $this->expectException(StaleBookingWriteException::class);

        $this->repository()->save($staleCopy);
    }

    public function test_appointment_date_and_time_columns_hold_exact_approved_types(): void
    {
        $columns = $this->connection()->select(
            "select column_name, data_type from information_schema.columns where table_name = 'bookings' and column_name in ('appointment_on', 'appointment_time', 'booking_reference', 'status')",
        );

        $types = [];
        foreach ($columns as $column) {
            $types[$column->column_name] = $column->data_type;
        }

        self::assertSame('date', $types['appointment_on']);
        self::assertSame('time without time zone', $types['appointment_time']);
        self::assertSame('character varying', $types['booking_reference']);
        self::assertSame('character varying', $types['status']);
    }

    private function booking(int $id = 1, ?string $patientEmail = 'aisyah@example.test', ?string $notes = 'First visit', ?string $reference = null, ?int $serviceId = 4): Booking
    {
        return Booking::submit(
            new BookingId($this->uuid($id)),
            new TenantId($this->uuid(2)),
            $serviceId === null ? null : new ServiceId($this->uuid($serviceId)),
            new BookingReference($reference ?? 'BOOK-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)),
            new PatientName('Aisyah Rahman'),
            new PatientPhone('+60123456789'),
            $patientEmail === null ? null : new PatientEmail($patientEmail),
            new AppointmentDate('2026-08-01'),
            new AppointmentTime('09:30'),
            $notes,
            $this->time(),
        );
    }

    private function repository(): PostgresBookingRepository
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
        return new DateTimeImmutable('2026-07-30T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
