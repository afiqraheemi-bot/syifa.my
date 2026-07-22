<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Application;

use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingSubmissionFailedException;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
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
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use App\Modules\Booking\Infrastructure\Transactions\PostgresBookingTransaction;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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
            self::markTestSkipped('Requires BOOKING_POSTGRES_TEST_DSN (or SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN) for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer', 'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        foreach (['booking_form_configurations', 'services', 'bookings'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }

        foreach ([
            'database/migrations/booking/2026_07_30_000001_create_bookings_table.php',
            'database/migrations/booking/2026_07_31_000001_create_services_table.php',
            'database/migrations/booking/2026_08_01_000001_create_booking_form_configurations_table.php',
            'database/migrations/booking/2026_08_02_000001_add_service_id_to_bookings_table.php',
            'database/migrations/booking/2026_08_03_000001_remove_clinic_id_from_bookings_table.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            array_unshift($this->migrations, $migration);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->migrations as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_core_only_submission_persists_a_tenant_owned_booking(): void
    {
        $this->saveConfiguration();

        $result = $this->application()->execute($this->command());
        $booking = $this->bookingRepository()->findById($this->tenant(), new BookingId($result->bookingId));

        self::assertNotNull($booking);
        self::assertSame($this->tenant()->value, $booking->tenantId->value);
        self::assertNull($booking->serviceId);
        self::assertNull($this->bookingRepository()->findById(new TenantId($this->uuid(2)), $booking->id));
    }

    public function test_active_service_submission_persists_validated_lineage(): void
    {
        $this->saveConfiguration(service: true);
        $this->serviceRepository()->save($this->service());

        $result = $this->application()->execute($this->command($this->uuid(4)));
        $booking = $this->bookingRepository()->findByReference($this->tenant(), $result->reference);

        self::assertNotNull($booking);
        self::assertSame($this->uuid(4), $booking->serviceId?->value);
    }

    public function test_optional_service_omission_persists_null(): void
    {
        $this->saveConfiguration(service: true);

        $result = $this->application()->execute($this->command());
        $booking = $this->bookingRepository()->findById($this->tenant(), new BookingId($result->bookingId));

        self::assertNotNull($booking);
        self::assertNull($booking->serviceId);
    }

    public function test_inactive_unknown_and_cross_tenant_services_persist_nothing(): void
    {
        $this->saveConfiguration(service: true);
        $inactive = $this->service();
        $inactive->deactivate($this->now());
        $this->serviceRepository()->save($inactive);

        foreach ([$this->uuid(4), $this->uuid(99)] as $serviceId) {
            try {
                $this->application(reference: 'BOOK-'.$serviceId)->execute($this->command($serviceId));
                self::fail('Expected Service validation to fail.');
            } catch (BookingServiceInactiveException|BookingServiceNotFoundException) {
                self::assertSame(0, $this->connection()->table('bookings')->count());
            }
        }

        $this->serviceRepository()->save($this->service(tenantId: 2, id: 5));
        try {
            $this->application(reference: 'BOOK-CROSS-TENANT')->execute($this->command($this->uuid(5)));
            self::fail('Expected cross-Tenant Service validation to fail.');
        } catch (BookingServiceNotFoundException $exception) {
            self::assertSame('The requested Service is unavailable.', $exception->getMessage());
            self::assertSame(0, $this->connection()->table('bookings')->count());
        }
    }

    public function test_missing_configuration_persists_nothing(): void
    {
        try {
            $this->application()->execute($this->command());
            self::fail('Expected missing configuration to fail.');
        } catch (BookingFormConfigurationNotFoundException) {
            self::assertSame(0, $this->connection()->table('bookings')->count());
        }
    }

    public function test_outer_transaction_rolls_back_a_save_when_repository_then_fails(): void
    {
        $this->saveConfiguration();
        $repository = new SaveThenFailBookingRepository($this->bookingRepository());

        try {
            $this->application($repository)->execute($this->command());
            self::fail('Expected translated persistence failure.');
        } catch (BookingSubmissionFailedException) {
            self::assertSame(0, $this->connection()->table('bookings')->count());
        }
    }

    private function application(?BookingRepositoryInterface $bookings = null, string $reference = 'BOOK-INTEGRATION-0001'): SubmitBookingService
    {
        return new SubmitBookingService(
            new PostgresBookingFormConfigurationRepository($this->connection(), new BookingFormConfigurationPersistenceMapper),
            $this->serviceRepository(),
            $bookings ?? $this->bookingRepository(),
            new PostgresBookingTransaction($this->connection()),
            new IntegrationFixedClock($this->now()),
            new IntegrationFixedIdentifier($this->uuid(10)),
            new IntegrationFixedReference($reference),
        );
    }

    private function saveConfiguration(bool $service = false): void
    {
        $order = [BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime];
        if ($service) {
            $order[] = BookingFormField::Service;
        }
        $configuration = BookingFormConfiguration::create($this->tenant(), $service, false, false, false, false, new RequiredFields([]), new FieldOrder($order), new FieldLabels([]), $this->now());
        (new PostgresBookingFormConfigurationRepository($this->connection(), new BookingFormConfigurationPersistenceMapper))->save($configuration);
    }

    private function service(int $tenantId = 1, int $id = 4): Service
    {
        return Service::register(new ServiceId($this->uuid($id)), new TenantId($this->uuid($tenantId)), new ServiceName('Consultation '.$id), null, null, new SortOrder(1), $this->now());
    }

    private function command(?string $serviceId = null): SubmitBookingCommand
    {
        return new SubmitBookingCommand($this->tenant(), 'Aisyah Rahman', '+60123456789', '2026-08-10', '10:30', $serviceId);
    }

    private function bookingRepository(): PostgresBookingRepository
    {
        return new PostgresBookingRepository($this->connection(), new BookingPersistenceMapper);
    }

    private function serviceRepository(): PostgresServiceRepository
    {
        return new PostgresServiceRepository($this->connection(), new ServicePersistenceMapper);
    }

    private function tenant(): TenantId
    {
        return new TenantId($this->uuid(1));
    }

    private function connection(): ConnectionInterface
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

final readonly class SaveThenFailBookingRepository implements BookingRepositoryInterface
{
    public function __construct(private BookingRepositoryInterface $inner) {}

    public function findById(TenantId $tenantId, BookingId $bookingId): ?Booking
    {
        return $this->inner->findById($tenantId, $bookingId);
    }

    public function findByReference(TenantId $tenantId, string $reference): ?Booking
    {
        return $this->inner->findByReference($tenantId, $reference);
    }

    public function save(Booking $booking): void
    {
        $this->inner->save($booking);
        throw new RuntimeException('fail after save');
    }
}

final readonly class IntegrationFixedClock implements BookingClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final readonly class IntegrationFixedIdentifier implements BookingIdentifierGeneratorInterface
{
    public function __construct(private string $value) {}

    public function generate(): string
    {
        return $this->value;
    }
}

final readonly class IntegrationFixedReference implements BookingReferenceGeneratorInterface
{
    public function __construct(private string $value) {}

    public function generate(): string
    {
        return $this->value;
    }
}
