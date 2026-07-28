<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application;

use App\Modules\Booking\Application\Configuration\ManageBookingFormConfigurationService;
use App\Modules\Booking\Application\Configuration\UpdateBookingFormConfigurationCommand;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ManageBookingFormConfigurationServiceTest extends TestCase
{
    public function test_it_initializes_a_missing_configuration_with_a_safe_idempotent_default(): void
    {
        $repository = new InMemoryBookingConfigurationRepository;
        $manager = new ManageBookingFormConfigurationService(
            $repository,
            new FixedActiveServiceRepository([]),
        );

        $first = $manager->read($this->uuid(1))->toArray();
        $second = $manager->read($this->uuid(1))->toArray();

        self::assertFalse($first['service_selection_enabled']);
        self::assertFalse($first['service_required']);
        self::assertFalse($first['email_enabled']);
        self::assertFalse($first['email_required']);
        self::assertFalse($first['notes_enabled']);
        self::assertFalse($first['notes_required']);
        self::assertSame(
            ['patient_name', 'phone', 'appointment_date', 'appointment_time'],
            $first['field_order'],
        );
        self::assertSame(1, $first['version']);
        self::assertSame($first, $second);
        self::assertSame(1, $repository->saveCount);
    }

    public function test_it_reads_updates_and_preserves_the_active_service_preview(): void
    {
        $configuration = $this->configuration();
        $service = Service::register(
            new ServiceId($this->uuid(20)),
            new TenantId($this->uuid(1)),
            new ServiceName('Consultation'),
            null,
            new SortOrder(1),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $repository = new InMemoryBookingConfigurationRepository($configuration);
        $manager = new ManageBookingFormConfigurationService(
            $repository,
            new FixedActiveServiceRepository([$service]),
        );

        $result = $manager->update(new UpdateBookingFormConfigurationCommand(
            $this->uuid(1),
            1,
            true,
            true,
            true,
            false,
            true,
            true,
            ['patient_name', 'phone', 'service', 'appointment_date', 'appointment_time', 'email', 'notes'],
            ['service' => 'Choose a service', 'notes' => 'Additional notes'],
        ))->toArray();

        self::assertTrue($result['service_selection_enabled']);
        self::assertTrue($result['service_required']);
        self::assertTrue($result['notes_required']);
        self::assertSame('Choose a service', $result['labels']['service']);
        self::assertSame('Consultation', $result['active_services'][0]['name']);
        self::assertSame(2, $result['version']);
    }

    public function test_required_disabled_field_is_rejected_atomically(): void
    {
        $configuration = $this->configuration();
        $manager = new ManageBookingFormConfigurationService(
            new InMemoryBookingConfigurationRepository($configuration),
            new FixedActiveServiceRepository([]),
        );

        $this->expectException(InvalidBookingFormConfigurationValueException::class);
        $manager->update(new UpdateBookingFormConfigurationCommand(
            $this->uuid(1),
            1,
            false,
            true,
            false,
            false,
            false,
            false,
            ['patient_name', 'phone', 'appointment_date', 'appointment_time'],
            [],
        ));
    }

    private function configuration(): BookingFormConfiguration
    {
        $configuration = BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
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
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $configuration->synchronizeVersion(1);

        return $configuration;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryBookingConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    public int $saveCount = 0;

    public function __construct(private ?BookingFormConfiguration $configuration = null) {}

    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration
    {
        return $tenantId->value === $this->configuration?->tenantId->value
            ? $this->configuration
            : null;
    }

    public function save(BookingFormConfiguration $configuration): void
    {
        $this->saveCount++;
        $configuration->synchronizeVersion($configuration->version() + 1);
        $this->configuration = $configuration;
    }
}

final readonly class FixedActiveServiceRepository implements ServiceRepositoryInterface
{
    /** @param list<Service> $services */
    public function __construct(private array $services) {}

    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
    {
        foreach ($this->services as $service) {
            if ($service->id->value === $serviceId->value) {
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
}
