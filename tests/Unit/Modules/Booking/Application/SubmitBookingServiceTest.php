<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application;

use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingSubmissionFailedException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\DurationMinutes;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubmitBookingServiceTest extends TestCase
{
    public function test_valid_core_only_submission_returns_a_deterministic_result(): void
    {
        [$service, $bookings] = $this->application($this->configuration());

        $result = $service->execute($this->command());

        self::assertSame($this->uuid(10), $result->bookingId);
        self::assertSame('BOOK-TEST-0001', $result->reference);
        self::assertSame('submitted', $result->status);
        self::assertSame($this->now()->format(DATE_ATOM), $result->createdAt->format(DATE_ATOM));
        self::assertCount(1, $bookings->saved);
        self::assertSame($this->uuid(1), $bookings->saved[0]->tenantId->value);
        self::assertNull($bookings->saved[0]->serviceId);
    }

    public function test_active_tenant_owned_service_is_validated_and_retained(): void
    {
        $tenant = new TenantId($this->uuid(1));
        $services = new InMemoryServiceRepository([$this->activeService($tenant)]);
        [$application, $bookings] = $this->application($this->configuration(service: true), $services);

        $application->execute($this->command(serviceId: $this->uuid(4)));

        self::assertSame($this->uuid(4), $bookings->saved[0]->serviceId?->value);
        self::assertSame([[$this->uuid(1), $this->uuid(4)]], $services->lookups);
    }

    public function test_optional_enabled_values_may_be_omitted(): void
    {
        [$application, $bookings] = $this->application($this->configuration(service: true, email: true, notes: true));

        $application->execute($this->command());

        self::assertNull($bookings->saved[0]->serviceId);
        self::assertNull($bookings->saved[0]->patientEmail);
        self::assertNull($bookings->saved[0]->notes);
    }

    #[DataProvider('requiredFieldProvider')]
    public function test_required_optional_value_must_be_supplied(BookingFormField $field): void
    {
        [$application, $bookings] = $this->application($this->configuration(
            service: $field === BookingFormField::Service,
            email: $field === BookingFormField::Email,
            notes: $field === BookingFormField::Notes,
            required: [$field],
        ));

        try {
            $application->execute($this->command());
            self::fail('Expected required-value validation to fail.');
        } catch (RequiredBookingFieldMissingException) {
            self::assertSame([], $bookings->saved);
        }
    }

    /** @return iterable<string, array{BookingFormField}> */
    public static function requiredFieldProvider(): iterable
    {
        yield 'service' => [BookingFormField::Service];
        yield 'email' => [BookingFormField::Email];
        yield 'notes' => [BookingFormField::Notes];
    }

    #[DataProvider('disabledFieldProvider')]
    public function test_disabled_optional_value_is_rejected(string $serviceId, ?string $email, ?string $notes): void
    {
        [$application, $bookings] = $this->application($this->configuration());

        try {
            $application->execute($this->command($serviceId === '' ? null : $serviceId, $email, $notes));
            self::fail('Expected disabled-value validation to fail.');
        } catch (DisabledBookingFieldSuppliedException) {
            self::assertSame([], $bookings->saved);
        }
    }

    /** @return iterable<string, array{string, ?string, ?string}> */
    public static function disabledFieldProvider(): iterable
    {
        yield 'service' => ['00000000-0000-4000-8000-000000000004', null, null];
        yield 'email' => ['', 'patient@example.test', null];
        yield 'notes' => ['', null, 'Please call'];
    }

    public function test_unknown_and_cross_tenant_services_share_the_same_fail_closed_outcome(): void
    {
        $otherTenantService = $this->activeService(new TenantId($this->uuid(2)));
        $services = new InMemoryServiceRepository([$otherTenantService]);
        [$application, $bookings] = $this->application($this->configuration(service: true), $services);

        try {
            $application->execute($this->command(serviceId: $this->uuid(4)));
            self::fail('Expected unavailable Service validation to fail.');
        } catch (BookingServiceNotFoundException $exception) {
            self::assertSame('The requested Service is unavailable.', $exception->getMessage());
            self::assertSame([], $bookings->saved);
        }
    }

    public function test_inactive_service_is_rejected(): void
    {
        $service = $this->activeService(new TenantId($this->uuid(1)));
        $service->deactivate($this->now());
        [$application, $bookings] = $this->application($this->configuration(service: true), new InMemoryServiceRepository([$service]));

        try {
            $application->execute($this->command(serviceId: $this->uuid(4)));
            self::fail('Expected inactive Service validation to fail.');
        } catch (BookingServiceInactiveException) {
            self::assertSame([], $bookings->saved);
        }
    }

    public function test_missing_or_foreign_configuration_is_never_substituted(): void
    {
        $foreign = $this->configuration(tenantId: 2);
        [$application, $bookings] = $this->application($foreign);

        $this->expectException(BookingFormConfigurationNotFoundException::class);
        try {
            $application->execute($this->command());
        } finally {
            self::assertSame([], $bookings->saved);
        }
    }

    public function test_repository_failure_is_translated_and_returns_no_result(): void
    {
        $bookings = new InMemoryBookingRepository;
        $bookings->failure = new RuntimeException('database detail');
        [$application] = $this->application($this->configuration(), bookings: $bookings);

        $this->expectException(BookingSubmissionFailedException::class);
        $this->expectExceptionMessage('Booking submission could not be completed.');
        $application->execute($this->command());
    }

    public function test_invalid_core_domain_input_persists_nothing(): void
    {
        [$application, $bookings] = $this->application($this->configuration());

        try {
            $application->execute(new SubmitBookingCommand(
                new TenantId($this->uuid(1)),
                '   ',
                '+60123456789',
                '2026-08-10',
                '10:30',
            ));
            self::fail('Expected Domain validation to fail.');
        } catch (InvalidBookingValueException) {
            self::assertSame([], $bookings->saved);
        }
    }

    /** @param list<BookingFormField> $required */
    private function configuration(
        bool $service = false,
        bool $email = false,
        bool $notes = false,
        array $required = [],
        int $tenantId = 1,
    ): BookingFormConfiguration {
        $order = [
            BookingFormField::PatientName,
            BookingFormField::Phone,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
        ];
        foreach ([[BookingFormField::Service, $service], [BookingFormField::Email, $email], [BookingFormField::Notes, $notes]] as [$field, $enabled]) {
            if ($enabled) {
                $order[] = $field;
            }
        }

        return BookingFormConfiguration::create(
            new TenantId($this->uuid($tenantId)),
            $service,
            false,
            $email,
            false,
            $notes,
            new RequiredFields($required),
            new FieldOrder($order),
            new FieldLabels([]),
            $this->now(),
        );
    }

    private function activeService(TenantId $tenantId): Service
    {
        return Service::register(
            new ServiceId($this->uuid(4)),
            $tenantId,
            new ServiceName('Consultation'),
            new ServiceDescription('Initial consultation'),
            new DurationMinutes(30),
            new SortOrder(1),
            $this->now(),
        );
    }

    private function command(?string $serviceId = null, ?string $email = null, ?string $notes = null): SubmitBookingCommand
    {
        return new SubmitBookingCommand(
            new TenantId($this->uuid(1)),
            'Aisyah Rahman',
            '+60123456789',
            '2026-08-10',
            '10:30',
            $serviceId,
            $email,
            $notes,
        );
    }

    /** @return array{SubmitBookingService, InMemoryBookingRepository} */
    private function application(
        BookingFormConfiguration $configuration,
        ?InMemoryServiceRepository $services = null,
        ?InMemoryBookingRepository $bookings = null,
    ): array {
        $bookings ??= new InMemoryBookingRepository;

        return [
            new SubmitBookingService(
                new InMemoryConfigurationRepository([$configuration]),
                $services ?? new InMemoryServiceRepository([]),
                $bookings,
                new ImmediateBookingTransaction,
                new FixedBookingClock($this->now()),
                new FixedBookingIdentifierGenerator($this->uuid(10)),
                new FixedBookingReferenceGenerator('BOOK-TEST-0001'),
            ),
            $bookings,
        ];
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

final class InMemoryConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    /** @param list<BookingFormConfiguration> $configurations */
    public function __construct(private array $configurations) {}

    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration
    {
        foreach ($this->configurations as $configuration) {
            if ($configuration->tenantId->value === $tenantId->value) {
                return $configuration;
            }
        }

        return null;
    }

    public function save(BookingFormConfiguration $configuration): void {}
}

final class InMemoryServiceRepository implements ServiceRepositoryInterface
{
    /** @var list<array{string, string}> */
    public array $lookups = [];

    /** @param list<Service> $services */
    public function __construct(private array $services) {}

    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
    {
        $this->lookups[] = [$tenantId->value, $serviceId->value];
        foreach ($this->services as $service) {
            if ($service->tenantId->value === $tenantId->value && $service->id->value === $serviceId->value) {
                return $service;
            }
        }

        return null;
    }

    public function findAll(TenantId $tenantId): array
    {
        return [];
    }

    public function findActive(TenantId $tenantId): array
    {
        return [];
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        return false;
    }

    public function save(Service $service): void {}
}

final class InMemoryBookingRepository implements BookingRepositoryInterface
{
    /** @var list<Booking> */
    public array $saved = [];

    public ?RuntimeException $failure = null;

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
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->saved[] = $booking;
    }
}

final readonly class ImmediateBookingTransaction implements BookingTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final readonly class FixedBookingClock implements BookingClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final readonly class FixedBookingIdentifierGenerator implements BookingIdentifierGeneratorInterface
{
    public function __construct(private string $identifier) {}

    public function generate(): string
    {
        return $this->identifier;
    }
}

final readonly class FixedBookingReferenceGenerator implements BookingReferenceGeneratorInterface
{
    public function __construct(private string $reference) {}

    public function generate(): string
    {
        return $this->reference;
    }
}
