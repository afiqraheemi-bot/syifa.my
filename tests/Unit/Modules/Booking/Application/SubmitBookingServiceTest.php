<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingOwnerAuthorization;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\Commands\CreateManualBookingCommand;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\CreateBookingWorkflow;
use App\Modules\Booking\Application\CreateManualBookingService;
use App\Modules\Booking\Application\Exceptions\BookingOperationForbiddenException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\UnsupportedManualBookingSourceException;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperatingIntervalData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\BookingHistoryEntry;
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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubmitBookingServiceTest extends TestCase
{
    public function test_submission_validates_slot_reserves_capacity_and_persists_snapshot_and_history(): void
    {
        $fixture = new SubmitBookingFixture;

        $result = $fixture->application()->execute($fixture->command());

        self::assertSame($fixture->uuid(10), $result->bookingId);
        self::assertCount(1, $fixture->bookings);
        self::assertSame('10:30', $fixture->bookings[0]->scheduledAppointment()->localEnd->value);
        self::assertSame('Asia/Kuala_Lumpur', $fixture->bookings[0]->scheduledAppointment()->timezone);
        self::assertSame(1, $fixture->reservations);
        self::assertCount(1, $fixture->history);
        self::assertSame('WEBSITE', $fixture->bookings[0]->source->value);
        self::assertSame('public_visitor', $fixture->history[0]->actorType->value);
        self::assertSame('WEBSITE', $fixture->history[0]->payload['source']);
    }

    public function test_all_governed_manual_sources_use_the_shared_workflow_and_record_owner_actor(): void
    {
        foreach (['WHATSAPP', 'PHONE', 'WALK_IN', 'STAFF'] as $index => $source) {
            $fixture = new SubmitBookingFixture;
            $service = new CreateManualBookingService($fixture->workflow(), new BookingOwnerAuthorization);
            $service->execute($fixture->manualCommand($source));

            self::assertSame($source, $fixture->bookings[0]->source->value, (string) $index);
            self::assertSame('clinic_owner', $fixture->history[0]->actorType->value);
            self::assertSame($fixture->uuid(20), $fixture->history[0]->actorId);
            self::assertSame($source, $fixture->history[0]->payload['source']);
        }
    }

    public function test_manual_website_source_is_rejected_before_reservation(): void
    {
        $fixture = new SubmitBookingFixture;
        $service = new CreateManualBookingService($fixture->workflow(), new BookingOwnerAuthorization);
        $this->expectException(UnsupportedManualBookingSourceException::class);
        try {
            $service->execute($fixture->manualCommand('WEBSITE'));
        } finally {
            self::assertSame(0, $fixture->reservations);
        }
    }

    public function test_website_designer_and_cross_tenant_owner_are_denied_without_leaking_data(): void
    {
        $actor = '00000000-0000-4000-8000-000000000020';
        foreach ([['website_designer', 1, $actor], ['clinic_owner', 2, $actor], ['clinic_owner', 1, '']] as [$role, $actorTenant, $actorId]) {
            $fixture = new SubmitBookingFixture;
            $service = new CreateManualBookingService($fixture->workflow(), new BookingOwnerAuthorization);
            try {
                $service->execute($fixture->manualCommand('PHONE', $role, $actorTenant, $actorId));
                self::fail('Expected authorization failure.');
            } catch (BookingOperationForbiddenException $exception) {
                self::assertSame('Booking operation is not authorized.', $exception->getMessage());
                self::assertSame([], $fixture->bookings);
            }
        }
    }

    public function test_missing_service_fails_closed_before_capacity_is_reserved(): void
    {
        $fixture = new SubmitBookingFixture;
        $fixture->services = [];

        $this->expectException(BookingServiceNotFoundException::class);
        try {
            $fixture->application()->execute($fixture->command());
        } finally {
            self::assertSame(0, $fixture->reservations);
            self::assertSame([], $fixture->bookings);
        }
    }
}

final class SubmitBookingFixture implements BookingClockInterface, BookingHistoryIdentifierGeneratorInterface, BookingHistoryRepositoryInterface, BookingIdentifierGeneratorInterface, BookingReferenceGeneratorInterface, BookingTransactionInterface, ClinicOperationalTimeReaderInterface, SlotCapacityReservationInterface
{
    /** @var list<Booking> */
    public array $bookings = [];

    /** @var list<BookingHistoryEntry> */
    public array $history = [];

    /** @var list<Service> */
    public array $services;

    public int $reservations = 0;

    public function __construct()
    {
        $this->services = [Service::register(new ServiceId($this->uuid(4)), new TenantId($this->uuid(1)), new ServiceName('Consultation'), null, new SortOrder(1), $this->now())];
    }

    public function application(): SubmitBookingService
    {
        return new SubmitBookingService($this->workflow());
    }

    public function workflow(): CreateBookingWorkflow
    {
        return new CreateBookingWorkflow(new FixtureConfigurationRepository($this), new FixtureServiceRepository($this), new FixtureBookingRepository($this), $this, $this, $this, $this, $this, new ClinicSlotGenerator, $this, $this, $this);
    }

    public function command(): SubmitBookingCommand
    {
        return new SubmitBookingCommand(new TenantId($this->uuid(1)), 'Aisyah Rahman', '+60123456789', '2026-08-10', '10:00', $this->uuid(4));
    }

    public function manualCommand(string $source, string $role = 'clinic_owner', int $actorTenant = 1, ?string $actorId = null): CreateManualBookingCommand
    {
        return new CreateManualBookingCommand(new TenantId($this->uuid(1)), new TenantId($this->uuid($actorTenant)), $actorId ?? $this->uuid(20), $role, $source, 'Aisyah Rahman', '+60123456789', '2026-08-10', '10:00', $this->uuid(4));
    }

    public function configuration(TenantId $tenantId): BookingFormConfiguration
    {
        return BookingFormConfiguration::create($tenantId, true, false, false, false, false, new RequiredFields([BookingFormField::Service]), new FieldOrder([BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime, BookingFormField::Service]), new FieldLabels([]), $this->now());
    }

    public function run(callable $callback): mixed
    {
        return $callback();
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-03T10:00:00Z');
    }

    public function generate(): string
    {
        return count($this->history) === 0 ? $this->uuid(10) : $this->uuid(11);
    }

    public function forTrustedTenant(string $tenantId): ClinicOperationalTimeData
    {
        return new ClinicOperationalTimeData($this->uuid(2), $tenantId, 'Asia/Kuala_Lumpur', [new ClinicOperatingIntervalData(1, '09:00', '12:00')], 30, 2);
    }

    public function reserve(string $tenantId, ReservationSlotData $slot, int $capacity): void
    {
        $this->reservations++;
    }

    public function release(string $tenantId, ReservationSlotData $slot): void {}

    public function isAvailable(string $tenantId, ReservationSlotData $slot): bool
    {
        return true;
    }

    public function append(BookingHistoryEntry $entry): void
    {
        $this->history[] = $entry;
    }

    public function forBooking(TenantId $tenantId, BookingId $bookingId): array
    {
        return $this->history;
    }

    public function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final readonly class FixtureConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    public function __construct(private SubmitBookingFixture $fixture) {}

    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration
    {
        return $this->fixture->configuration($tenantId);
    }

    public function save(BookingFormConfiguration $configuration): void {}
}

final readonly class FixtureServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(private SubmitBookingFixture $fixture) {}

    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
    {
        foreach ($this->fixture->services as $service) {
            if ($service->tenantId->value === $tenantId->value && $service->id->value === $serviceId->value) {
                return $service;
            }
        }

        return null;
    }

    public function findAll(TenantId $tenantId): array
    {
        return $this->fixture->services;
    }

    public function findActive(TenantId $tenantId): array
    {
        return $this->fixture->services;
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        return false;
    }

    public function save(Service $service): void {}
}

final readonly class FixtureBookingRepository implements BookingRepositoryInterface
{
    public function __construct(private SubmitBookingFixture $fixture) {}

    public function findById(TenantId $tenantId, BookingId $bookingId): ?Booking
    {
        return null;
    }

    public function findByReference(TenantId $tenantId, string $reference): ?Booking
    {
        return null;
    }

    public function save(Booking $booking): void
    {
        $this->fixture->bookings[] = $booking;
    }
}
