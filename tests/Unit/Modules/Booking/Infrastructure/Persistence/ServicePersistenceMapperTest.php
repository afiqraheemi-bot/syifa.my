<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\DurationMinutes;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\ServiceStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ServicePersistenceMapperTest extends TestCase
{
    public function test_maps_domain_to_storage_record(): void
    {
        $service = $this->service();
        $mapper = new ServicePersistenceMapper;

        $record = $mapper->record($service);

        self::assertSame($this->uuid(1), $record->id);
        self::assertSame($this->uuid(2), $record->tenantId);
        self::assertSame('Dental Cleaning', $record->name);
        self::assertSame('Routine cleaning and checkup', $record->description);
        self::assertSame(30, $record->durationMinutes);
        self::assertSame(1, $record->sortOrder);
        self::assertSame('active', $record->status);
        self::assertSame(0, $record->version);
    }

    public function test_reconstitutes_immutable_domain_with_version(): void
    {
        $mapper = new ServicePersistenceMapper;
        $record = new ServiceStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            'Dental Cleaning',
            'Routine cleaning and checkup',
            30,
            1,
            'inactive',
            $this->occurredAt(),
            $this->occurredAt(),
            9,
        );

        $service = $mapper->toDomain($record);

        self::assertSame($this->uuid(1), $service->id->value);
        self::assertSame('inactive', $service->status()->value);
        self::assertSame(9, $service->version());
    }

    public function test_reconstitutes_a_service_without_description_or_duration(): void
    {
        $mapper = new ServicePersistenceMapper;
        $record = new ServiceStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            'Dental Cleaning',
            null,
            null,
            1,
            'active',
            $this->occurredAt(),
            $this->occurredAt(),
            1,
        );

        $service = $mapper->toDomain($record);

        self::assertNull($service->description);
        self::assertNull($service->durationMinutes);
    }

    private function service(): Service
    {
        return Service::register(
            new ServiceId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ServiceName('Dental Cleaning'),
            new ServiceDescription('Routine cleaning and checkup'),
            new DurationMinutes(30),
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
