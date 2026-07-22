<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
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
        foreach (['2026_07_30_000001_create_bookings_table.php', '2026_07_31_000001_create_services_table.php', '2026_08_01_000001_create_booking_form_configurations_table.php', '2026_08_02_000001_add_service_id_to_bookings_table.php', '2026_08_03_000001_remove_clinic_id_from_bookings_table.php', '2026_08_05_000001_add_booking_mvp_scheduling.php', '2026_08_05_000002_create_booking_capacity_and_history.php', '2026_08_05_000003_remove_service_duration.php'] as $file) {
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

    public function test_separate_postgresql_connections_cannot_exceed_final_capacity(): void
    {
        $slot = new ReservationSlotData(new DateTimeImmutable('2026-08-10T02:00:00Z'), new DateTimeImmutable('2026-08-10T02:30:00Z'));
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
                    $connection->transaction(fn (): mixed => (new PostgresSlotCapacityReservation($connection))->reserve($this->uuid(1), $slot, 1));
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
    }

    private function application(int $id): SubmitBookingService
    {
        $fixed = new PostgresBookingFixedValues($this->uuid($id), 'BOOK-'.$id, $this->now());

        return new SubmitBookingService(new PostgresBookingFormConfigurationRepository($this->db(), new BookingFormConfigurationPersistenceMapper), new PostgresServiceRepository($this->db(), new ServicePersistenceMapper), new PostgresBookingRepository($this->db(), new BookingPersistenceMapper), new PostgresBookingTransaction($this->db()), $fixed, $fixed, $fixed, $fixed, new ClinicSlotGenerator, new PostgresSlotCapacityReservation($this->db()), new PostgresBookingHistoryRepository($this->db()), $fixed);
    }

    private function command(): SubmitBookingCommand
    {
        return new SubmitBookingCommand(new TenantId($this->uuid(1)), 'Aisyah', '+6012', '2026-08-10', '10:00', $this->uuid(4));
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
