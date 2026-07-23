<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\CreateBookingWorkflow;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
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
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingSubmissionGatewayAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BookingSubmissionGatewayAdapterTest extends TestCase
{
    public function test_successful_submission_returns_a_result_with_no_booking_id_property(): void
    {
        $fixture = new GatewayFixture;

        $result = $fixture->adapter()->submit($fixture->submission());

        self::assertSame('BOOK-REF-1', $result->reference);
        self::assertSame('submitted', $result->status);
        self::assertFalse(property_exists($result, 'bookingId'));
    }

    public function test_missing_consent_is_mapped_to_a_public_validation_exception_never_the_internal_message(): void
    {
        $fixture = new GatewayFixture;

        try {
            $fixture->adapter()->submit($fixture->submission(consent: false));
            self::fail('Expected a PublicBookingValidationException.');
        } catch (PublicBookingValidationException $exception) {
            self::assertSame('Enter a phone number we can reach you on.', $exception->getMessage());
            self::assertStringNotContainsString('consent', $exception->getMessage());
        }
    }

    public function test_missing_service_is_mapped_to_a_public_business_rule_exception(): void
    {
        $fixture = new GatewayFixture;
        $fixture->services = [];

        try {
            $fixture->adapter()->submit($fixture->submission());
            self::fail('Expected a PublicBookingBusinessRuleException.');
        } catch (PublicBookingBusinessRuleException $exception) {
            self::assertSame("This option isn't available right now. Please choose another.", $exception->getMessage());
        }
    }

    public function test_inactive_service_is_mapped_to_a_public_business_rule_exception(): void
    {
        $fixture = new GatewayFixture;
        $fixture->services[0]->deactivate($fixture->now());

        try {
            $fixture->adapter()->submit($fixture->submission());
            self::fail('Expected a PublicBookingBusinessRuleException.');
        } catch (PublicBookingBusinessRuleException $exception) {
            self::assertSame("This option isn't available right now. Please choose another.", $exception->getMessage());
        }
    }

    public function test_full_slot_is_mapped_to_a_public_availability_exception(): void
    {
        $fixture = new GatewayFixture;
        $fixture->slotUnavailable = true;

        try {
            $fixture->adapter()->submit($fixture->submission());
            self::fail('Expected a PublicBookingAvailabilityException.');
        } catch (PublicBookingAvailabilityException $exception) {
            self::assertSame('That time was just taken. Please choose another.', $exception->getMessage());
        }
    }

    public function test_an_unanticipated_failure_is_mapped_to_a_public_infrastructure_exception_never_leaking_the_cause(): void
    {
        $fixture = new GatewayFixture;
        $fixture->clockThrows = true;

        try {
            $fixture->adapter()->submit($fixture->submission());
            self::fail('Expected a PublicBookingInfrastructureException.');
        } catch (PublicBookingInfrastructureException $exception) {
            self::assertSame('Something went wrong on our end. Please try again.', $exception->getMessage());
            self::assertStringNotContainsString('a database column overflowed', $exception->getMessage());
        }
    }
}

final class GatewayFixture implements BookingClockInterface, BookingHistoryIdentifierGeneratorInterface, BookingHistoryRepositoryInterface, BookingIdentifierGeneratorInterface, BookingTransactionInterface, ClinicOperationalTimeReaderInterface, ServiceRepositoryInterface, SlotCapacityReservationInterface
{
    private const string TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private const string SERVICE_ID = '00000000-0000-4000-8000-000000000004';

    /** @var list<Service> */
    public array $services;

    public bool $slotUnavailable = false;

    public bool $clockThrows = false;

    public function __construct()
    {
        $this->services = [Service::register(new ServiceId(self::SERVICE_ID), new TenantId(self::TENANT_ID), new ServiceName('Consultation'), null, new SortOrder(1), $this->now())];
    }

    public function adapter(): BookingSubmissionGatewayAdapter
    {
        return new BookingSubmissionGatewayAdapter(new SubmitBookingService(new CreateBookingWorkflow(
            new GatewayFixtureConfigurationRepository,
            $this,
            new GatewayFixtureBookingRepository,
            $this,
            $this,
            $this,
            new GatewayFixtureReferenceGenerator,
            $this,
            new ClinicSlotGenerator,
            $this,
            $this,
            $this,
        )));
    }

    public function submission(bool $consent = true): PublicBookingSubmission
    {
        return new PublicBookingSubmission(self::TENANT_ID, 'Aisyah Rahman', '+60123456789', '2026-08-10', '10:00', $consent, self::SERVICE_ID);
    }

    public function now(): DateTimeImmutable
    {
        if ($this->clockThrows) {
            throw new RuntimeException('a database column overflowed');
        }

        return new DateTimeImmutable('2026-08-03T10:00:00Z');
    }

    public function generate(): string
    {
        return '00000000-0000-4000-8000-000000000010';
    }

    public function forTrustedTenant(string $tenantId): ClinicOperationalTimeData
    {
        return new ClinicOperationalTimeData('00000000-0000-4000-8000-000000000002', $tenantId, 'Asia/Kuala_Lumpur', [new ClinicOperatingIntervalData(1, '09:00', '12:00')], 30, 2);
    }

    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
    {
        foreach ($this->services as $service) {
            if ($service->tenantId->value === $tenantId->value && $service->id->value === $serviceId->value) {
                return $service;
            }
        }

        return null;
    }

    public function findAll(TenantId $tenantId): array
    {
        return $this->services;
    }

    public function findActive(TenantId $tenantId): array
    {
        return $this->services;
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        return false;
    }

    public function save(Service $service): void {}

    public function reserve(string $tenantId, ReservationSlotData $slot, int $capacity): void
    {
        if ($this->slotUnavailable) {
            throw new SlotUnavailableException('The requested slot is no longer available.');
        }
    }

    public function release(string $tenantId, ReservationSlotData $slot): void {}

    public function isAvailable(string $tenantId, ReservationSlotData $slot): bool
    {
        return ! $this->slotUnavailable;
    }

    public function append(BookingHistoryEntry $entry): void {}

    public function forBooking(TenantId $tenantId, BookingId $bookingId): array
    {
        return [];
    }

    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class GatewayFixtureBookingRepository implements BookingRepositoryInterface
{
    public function findById(TenantId $tenantId, BookingId $bookingId): ?Booking
    {
        return null;
    }

    public function findByReference(TenantId $tenantId, string $reference): ?Booking
    {
        return null;
    }

    public function save(Booking $booking): void {}
}

final class GatewayFixtureConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration
    {
        return BookingFormConfiguration::create($tenantId, true, false, false, false, false, new RequiredFields([BookingFormField::Service]), new FieldOrder([BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime, BookingFormField::Service]), new FieldLabels([]), new DateTimeImmutable('2026-08-03T10:00:00Z'));
    }

    public function save(BookingFormConfiguration $configuration): void {}
}

final class GatewayFixtureReferenceGenerator implements BookingReferenceGeneratorInterface
{
    public function generate(): string
    {
        return 'BOOK-REF-1';
    }
}
