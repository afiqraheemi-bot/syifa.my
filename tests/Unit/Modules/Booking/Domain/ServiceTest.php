<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Domain;

use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\DurationMinutes;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\ServiceStatus;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function test_register_creates_an_active_service(): void
    {
        $service = $this->service();

        self::assertSame(ServiceStatus::Active, $service->status());
        self::assertSame($this->uuid(1), $service->id->value);
        self::assertSame($this->uuid(2), $service->tenantId->value);
        self::assertSame('Dental Cleaning', $service->name->value);
        self::assertSame('Routine cleaning and checkup', $service->description?->value);
        self::assertSame(30, $service->durationMinutes?->value);
        self::assertSame(1, $service->sortOrder->value);
        self::assertSame(0, $service->version());
    }

    public function test_description_and_duration_are_optional(): void
    {
        $service = $this->service(description: null, durationMinutes: null);

        self::assertNull($service->description);
        self::assertNull($service->durationMinutes);
    }

    public function test_created_at_and_updated_at_start_equal_at_registration(): void
    {
        $service = $this->service();

        self::assertSame($this->occurredAt()->format(DATE_ATOM), $service->createdAt->format(DATE_ATOM));
        self::assertSame($this->occurredAt()->format(DATE_ATOM), $service->updatedAt()->format(DATE_ATOM));
    }

    public function test_identity_fields_are_immutable(): void
    {
        $service = $this->service();

        $this->expectException(Error::class);

        // @phpstan-ignore-next-line - proving readonly identity is language-enforced.
        $service->id = new ServiceId($this->uuid(9));
    }

    public function test_deactivate_then_activate_transitions_status_and_updates_timestamp(): void
    {
        $service = $this->service();
        $later = $this->occurredAt()->modify('+1 day');

        $service->deactivate($later);

        self::assertSame(ServiceStatus::Inactive, $service->status());
        self::assertSame($later->format(DATE_ATOM), $service->updatedAt()->format(DATE_ATOM));

        $evenLater = $later->modify('+1 day');
        $service->activate($evenLater);

        self::assertSame(ServiceStatus::Active, $service->status());
        self::assertSame($evenLater->format(DATE_ATOM), $service->updatedAt()->format(DATE_ATOM));
    }

    public function test_deactivating_an_already_inactive_service_is_a_no_op(): void
    {
        $service = $this->service();
        $service->deactivate($this->occurredAt()->modify('+1 day'));
        $unchangedAt = $service->updatedAt();

        $service->deactivate($this->occurredAt()->modify('+2 days'));

        self::assertSame(ServiceStatus::Inactive, $service->status());
        self::assertSame($unchangedAt->format(DATE_ATOM), $service->updatedAt()->format(DATE_ATOM));
    }

    public function test_version_can_be_synchronized_for_optimistic_concurrency(): void
    {
        $service = $this->service();

        $service->synchronizeVersion(5);

        self::assertSame(5, $service->version());
    }

    public function test_service_id_rejects_a_non_uuid_value(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new ServiceId('not-a-uuid');
    }

    public function test_service_name_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new ServiceName('   ');
    }

    public function test_service_description_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new ServiceDescription('');
    }

    public function test_duration_minutes_rejects_a_non_positive_value(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new DurationMinutes(0);
    }

    public function test_duration_minutes_rejects_more_than_one_day(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new DurationMinutes(1441);
    }

    public function test_sort_order_rejects_a_negative_value(): void
    {
        $this->expectException(InvalidServiceValueException::class);

        new SortOrder(-1);
    }

    public function test_exists_by_name_is_scoped_to_the_owning_tenant(): void
    {
        $repository = new InMemoryServiceRepository;
        $repository->save($this->service());
        $repository->save($this->service(id: 2, tenantId: 3, name: 'Whitening'));

        self::assertTrue($repository->existsByName(new TenantId($this->uuid(2)), 'Dental Cleaning'));
        self::assertFalse($repository->existsByName(new TenantId($this->uuid(2)), 'Whitening'));
        self::assertTrue($repository->existsByName(new TenantId($this->uuid(3)), 'Whitening'));
        self::assertFalse($repository->existsByName(new TenantId($this->uuid(4)), 'Dental Cleaning'));
    }

    private function service(
        int $id = 1,
        int $tenantId = 2,
        string $name = 'Dental Cleaning',
        ?string $description = 'Routine cleaning and checkup',
        ?int $durationMinutes = 30,
    ): Service {
        return Service::register(
            new ServiceId($this->uuid($id)),
            new TenantId($this->uuid($tenantId)),
            new ServiceName($name),
            $description === null ? null : new ServiceDescription($description),
            $durationMinutes === null ? null : new DurationMinutes($durationMinutes),
            new SortOrder(1),
            $this->occurredAt(),
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-31T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryServiceRepository implements ServiceRepositoryInterface
{
    /** @var array<string, Service> */
    private array $services = [];

    public function findById(ServiceId $serviceId): ?Service
    {
        return $this->services[$serviceId->value] ?? null;
    }

    public function findAll(TenantId $tenantId): array
    {
        return array_values(array_filter(
            $this->services,
            static fn (Service $service): bool => $service->tenantId->value === $tenantId->value,
        ));
    }

    public function findActive(TenantId $tenantId): array
    {
        return array_values(array_filter(
            $this->services,
            static fn (Service $service): bool => $service->tenantId->value === $tenantId->value
                && $service->status() === ServiceStatus::Active,
        ));
    }

    public function existsByName(TenantId $tenantId, string $name): bool
    {
        foreach ($this->services as $service) {
            if ($service->tenantId->value === $tenantId->value && $service->name->value === $name) {
                return true;
            }
        }

        return false;
    }

    public function save(Service $service): void
    {
        $this->services[$service->id->value] = $service;
    }
}
