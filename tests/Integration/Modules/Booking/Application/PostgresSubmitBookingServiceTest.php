<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingOwnerAuthorization;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\CancelBookingService;
use App\Modules\Booking\Application\ClinicOwnerBookingOperations;
use App\Modules\Booking\Application\Commands\CreateManualBookingCommand;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\ConfirmBookingService;
use App\Modules\Booking\Application\CreateBookingWorkflow;
use App\Modules\Booking\Application\CreateManualBookingService;
use App\Modules\Booking\Application\Exceptions\BookingOperationNotFoundException;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\RescheduleBookingService;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperatingIntervalData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Queries\PostgresClinicOwnerBookingReadAdapter;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingFormConfigurationRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingHistoryRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresSlotCapacityReservation;
use App\Modules\Booking\Infrastructure\Transactions\PostgresBookingTransaction;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresSubmitBookingServiceTest extends TestCase
{
    private const string CONNECTION = 'booking_submission_postgres_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('BOOKING_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires a disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, ['driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer', 'timezone' => 'UTC']);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        foreach (['booking_history', 'booking_slot_reservation_buckets', 'booking_form_configurations', 'bookings', 'services', 'tenants'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
        Schema::connection(self::CONNECTION)->create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
        foreach (['2026_07_30_000001_create_bookings_table.php', '2026_07_31_000001_create_services_table.php', '2026_08_01_000001_create_booking_form_configurations_table.php', '2026_08_02_000001_add_service_id_to_bookings_table.php', '2026_08_03_000001_remove_clinic_id_from_bookings_table.php', '2026_08_05_000001_add_booking_mvp_scheduling.php', '2026_08_05_000002_create_booking_capacity_and_history.php', '2026_08_05_000003_remove_service_duration.php', '2026_08_06_000001_add_booking_source.php'] as $file) {
            $migration = require base_path('database/migrations/booking/'.$file);
            $migration->up();
            array_unshift($this->migrations, $migration);
        }
        $this->db()->table('tenants')->insert(['id' => $this->uuid(1)]);
        $tenant = new TenantId($this->uuid(1));
        (new PostgresServiceRepository($this->db(), new ServicePersistenceMapper))->save(Service::register(new ServiceId($this->uuid(4)), $tenant, new ServiceName('Consultation'), null, new SortOrder(1), $this->now()));
        (new PostgresBookingFormConfigurationRepository($this->db(), new BookingFormConfigurationPersistenceMapper))->save(BookingFormConfiguration::create($tenant, true, false, false, false, false, new RequiredFields([BookingFormField::Service]), new FieldOrder([BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime, BookingFormField::Service]), new FieldLabels([]), $this->now()));
    }

    protected function tearDown(): void
    {
        if ($this->connection === null) {
            parent::tearDown();

            return;
        }

        foreach ($this->migrations as $migration) {
            $migration->down();
        }
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_submission_atomically_persists_snapshot_capacity_and_history(): void
    {
        $result = $this->application(10)->execute($this->command());
        $row = $this->db()->table('bookings')->where('id', $result->bookingId)->first();
        self::assertNotNull($row);
        self::assertSame('Asia/Kuala_Lumpur', $row->timezone);
        self::assertSame(1, $this->db()->table('booking_slot_reservation_buckets')->value('reserved_count'));
        self::assertSame(1, $this->db()->table('booking_history')->where('event_type', 'BookingSubmitted')->count());
        $history = $this->db()->table('booking_history')->where('booking_id', $result->bookingId)->first();
        self::assertNotNull($history);
        $payload = json_decode((string) $history->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['consent_acknowledged']);
    }

    public function test_full_slot_rejects_second_submission_without_leaking_booking_or_history(): void
    {
        $this->application(10)->execute($this->command());
        $this->expectException(SlotUnavailableException::class);
        try {
            $this->application(11)->execute($this->command());
        } finally {
            self::assertSame(1, $this->db()->table('bookings')->count());
            self::assertSame(1, $this->db()->table('booking_history')->count());
        }
    }

    public function test_public_and_manual_paths_compete_for_the_same_capacity_bucket(): void
    {
        $this->application(10)->execute($this->command());
        $this->expectException(SlotUnavailableException::class);
        try {
            $this->manualApplication(11)->execute($this->manualCommand());
        } finally {
            self::assertSame(1, $this->db()->table('bookings')->count());
            self::assertSame(1, $this->db()->table('booking_slot_reservation_buckets')->value('reserved_count'));
        }
    }

    public function test_manual_booking_persists_source_and_clinic_owner_history_actor(): void
    {
        $result = $this->manualApplication(12)->execute($this->manualCommand('WHATSAPP'));

        self::assertSame('WHATSAPP', $this->db()->table('bookings')->where('id', $result->bookingId)->value('booking_source'));
        $history = $this->db()->table('booking_history')->where('booking_id', $result->bookingId)->first();
        self::assertNotNull($history);
        self::assertSame('clinic_owner', $history->actor_type);
        self::assertSame($this->uuid(20), $history->actor_id);
        self::assertStringContainsString('WHATSAPP', (string) $history->payload);
        $reader = new PostgresClinicOwnerBookingReadAdapter($this->db());
        $detail = $reader->detail($this->uuid(1), $result->bookingId);
        self::assertNotNull($detail);
        self::assertSame('WHATSAPP', $detail->source);
        self::assertSame('Aisyah', $detail->patientName);
        self::assertSame('+6012', $detail->patientPhone);
        self::assertSame('Consultation', $detail->serviceName);
        self::assertSame('Asia/Kuala_Lumpur', $detail->timezone);
        self::assertNull($reader->detail($this->uuid(9), $result->bookingId));
        self::assertCount(1, $reader->list($this->uuid(1), $result->status, null, 20, $result->reference, 'WHATSAPP'));
        self::assertSame([$result->status => 1], $reader->countByStatus($this->uuid(1)));
        self::assertSame(['WHATSAPP' => 1], $reader->countBySource($this->uuid(1)));
        self::assertSame([], $reader->countBySource($this->uuid(9)));
    }

    public function test_manual_booking_without_service_round_trips_when_service_selection_is_disabled(): void
    {
        $tenant = new TenantId($this->uuid(1));
        $configurations = new PostgresBookingFormConfigurationRepository(
            $this->db(),
            new BookingFormConfigurationPersistenceMapper,
        );
        $configuration = $configurations->findByTenant($tenant);
        self::assertNotNull($configuration);
        $configuration->reconfigure(
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels([]),
            $this->now(),
        );
        $configurations->save($configuration);

        $result = $this->manualApplication(13)->execute(new CreateManualBookingCommand(
            $tenant,
            $tenant,
            $this->uuid(20),
            'clinic_owner',
            'STAFF',
            'Aisyah',
            '+6012',
            '2026-08-10',
            '10:00',
            null,
        ));

        self::assertNull($this->db()->table('bookings')->where('id', $result->bookingId)->value('service_id'));
        self::assertNull((new PostgresClinicOwnerBookingReadAdapter($this->db()))
            ->detail($tenant->value, $result->bookingId)?->serviceId);
    }

    public function test_clinic_owner_operations_preserve_history_snapshots_and_capacity(): void
    {
        $result = $this->application(40)->execute($this->command());
        $operations = $this->operations();

        try {
            $operations->confirm($this->uuid(9), $result->bookingId, $this->uuid(20), 'clinic_owner');
            self::fail('Cross-tenant Booking operation must fail closed.');
        } catch (BookingOperationNotFoundException) {
            self::assertSame('submitted', $this->db()->table('bookings')->where('id', $result->bookingId)->value('status'));
            self::assertSame(1, $this->db()->table('booking_history')->where('booking_id', $result->bookingId)->count());
        }

        $operations->confirm($this->uuid(1), $result->bookingId, $this->uuid(20), 'clinic_owner');
        self::assertSame('confirmed', $this->db()->table('bookings')->where('id', $result->bookingId)->value('status'));

        $operations->reschedule($this->uuid(1), $result->bookingId, '2026-08-10', '10:30', $this->uuid(20), 'clinic_owner');
        $rescheduled = $this->db()->table('bookings')->where('id', $result->bookingId)->first();
        self::assertNotNull($rescheduled);
        self::assertSame('10:30:00', $rescheduled->appointment_time);
        self::assertSame('11:00:00', $rescheduled->local_end_time);
        self::assertSame(
            [0, 1],
            $this->db()->table('booking_slot_reservation_buckets')->orderBy('starts_at_utc')->pluck('reserved_count')->map(static fn (mixed $count): int => (int) $count)->all(),
        );

        $operations->cancel($this->uuid(1), $result->bookingId, $this->uuid(20), 'clinic_owner');
        self::assertSame('cancelled', $this->db()->table('bookings')->where('id', $result->bookingId)->value('status'));
        self::assertSame(
            [0, 0],
            $this->db()->table('booking_slot_reservation_buckets')->orderBy('starts_at_utc')->pluck('reserved_count')->map(static fn (mixed $count): int => (int) $count)->all(),
        );
        self::assertSame(
            ['BookingSubmitted', 'BookingConfirmed', 'AppointmentRescheduled', 'BookingCancelled'],
            $this->db()->table('booking_history')->where('booking_id', $result->bookingId)->orderBy('occurred_at')->orderBy('id')->pluck('event_type')->all(),
        );
        self::assertStringContainsString(
            '"old_local_start": "10:00"',
            (string) $this->db()->table('booking_history')->where('booking_id', $result->bookingId)->where('event_type', 'AppointmentRescheduled')->value('payload'),
        );

        $this->db()->table('booking_history')->delete();
        $this->db()->table('booking_slot_reservation_buckets')->delete();
        $this->db()->table('bookings')->delete();
    }

    public function test_separate_postgresql_connections_cannot_exceed_final_capacity(): void
    {
        DB::disconnect(self::CONNECTION);
        $children = [];
        for ($index = 0; $index < 2; $index++) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    $name = 'booking_capacity_child_'.$index;
                    config()->set('database.connections.'.$name, config('database.connections.'.self::CONNECTION));
                    DB::purge($name);
                    $connection = DB::connection($name);
                    if ($index === 0) {
                        $this->application(30, $connection)->execute($this->command());
                    } else {
                        $this->manualApplication(31, $connection)->execute($this->manualCommand());
                    }
                    exit(0);
                } catch (SlotUnavailableException) {
                    exit(2);
                } catch (\Throwable) {
                    exit(3);
                }
            }
            $children[] = $pid;
        }
        $codes = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $codes[] = pcntl_wexitstatus($status);
        }
        sort($codes);
        self::assertSame([0, 2], $codes);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        self::assertSame(1, $this->db()->table('booking_slot_reservation_buckets')->value('reserved_count'));
        self::assertSame(1, $this->db()->table('bookings')->count());
        self::assertSame(1, $this->db()->table('booking_history')->count());
    }

    private function application(int $id, ?ConnectionInterface $connection = null): SubmitBookingService
    {
        $connection ??= $this->db();
        $fixed = new PostgresBookingFixedValues($this->uuid($id), 'BOOK-'.$id, $this->now());

        return new SubmitBookingService($this->workflow($connection, $fixed));
    }

    private function manualApplication(int $id, ?ConnectionInterface $connection = null): CreateManualBookingService
    {
        $connection ??= $this->db();
        $fixed = new PostgresBookingFixedValues($this->uuid($id), 'BOOK-'.$id, $this->now());

        return new CreateManualBookingService($this->workflow($connection, $fixed), new BookingOwnerAuthorization);
    }

    private function workflow(ConnectionInterface $connection, PostgresBookingFixedValues $fixed): CreateBookingWorkflow
    {
        return new CreateBookingWorkflow(new PostgresBookingFormConfigurationRepository($connection, new BookingFormConfigurationPersistenceMapper), new PostgresServiceRepository($connection, new ServicePersistenceMapper), new PostgresBookingRepository($connection, new BookingPersistenceMapper), new PostgresBookingTransaction($connection), $fixed, $fixed, $fixed, $fixed, new ClinicSlotGenerator, new PostgresSlotCapacityReservation($connection), new PostgresBookingHistoryRepository($connection), $fixed);
    }

    private function operations(): ClinicOwnerBookingOperations
    {
        $bookings = new PostgresBookingRepository($this->db(), new BookingPersistenceMapper);
        $history = new PostgresBookingHistoryRepository($this->db());
        $capacity = new PostgresSlotCapacityReservation($this->db());
        $transactions = new PostgresBookingTransaction($this->db());
        $authorization = new BookingOwnerAuthorization;

        return new ClinicOwnerBookingOperations(
            new ConfirmBookingService($bookings, $history, $transactions, new PostgresBookingFixedValues($this->uuid(50), 'unused', $this->now()), new PostgresBookingFixedValues($this->uuid(50), 'unused', $this->now()), $authorization),
            new CancelBookingService($bookings, $history, $capacity, $transactions, new PostgresBookingFixedValues($this->uuid(52), 'unused', $this->now()), new PostgresBookingFixedValues($this->uuid(52), 'unused', $this->now()), $authorization),
            new RescheduleBookingService($bookings, $history, $capacity, new PostgresBookingFixedValues($this->uuid(51), 'unused', $this->now()), new ClinicSlotGenerator, $transactions, new PostgresBookingFixedValues($this->uuid(51), 'unused', $this->now()), new PostgresBookingFixedValues($this->uuid(51), 'unused', $this->now()), $authorization),
        );
    }

    private function command(): SubmitBookingCommand
    {
        return new SubmitBookingCommand($this->uuid(1), 'Aisyah', '+6012', '2026-08-10', '10:00', true, $this->uuid(4));
    }

    private function manualCommand(string $source = 'PHONE'): CreateManualBookingCommand
    {
        $tenant = new TenantId($this->uuid(1));

        return new CreateManualBookingCommand($tenant, $tenant, $this->uuid(20), 'clinic_owner', $source, 'Aisyah', '+6012', '2026-08-10', '10:00', $this->uuid(4));
    }

    private function db(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-03T10:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final readonly class PostgresBookingFixedValues implements BookingClockInterface, BookingHistoryIdentifierGeneratorInterface, BookingIdentifierGeneratorInterface, BookingReferenceGeneratorInterface, ClinicOperationalTimeReaderInterface
{
    public function __construct(private string $id, private string $reference, private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function generate(): string
    {
        return $this->id;
    }

    public function forTrustedTenant(string $tenantId): ClinicOperationalTimeData
    {
        return new ClinicOperationalTimeData('clinic', $tenantId, 'Asia/Kuala_Lumpur', [new ClinicOperatingIntervalData(1, '09:00', '12:00')], 30, 1);
    }
}
