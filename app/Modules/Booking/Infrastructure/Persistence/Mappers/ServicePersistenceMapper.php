<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Mappers;

use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\ServiceStatus;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Records\ServiceStorageRecord;

final class ServicePersistenceMapper
{
    public function record(Service $service): ServiceStorageRecord
    {
        return new ServiceStorageRecord(
            $service->id->value,
            $service->tenantId->value,
            $service->name->value,
            $service->description?->value,
            $service->sortOrder->value,
            $service->status()->value,
            $service->createdAt,
            $service->updatedAt(),
            $service->version(),
        );
    }

    public function toDomain(ServiceStorageRecord $record): Service
    {
        return new Service(
            id: new ServiceId($record->id),
            tenantId: new TenantId($record->tenantId),
            name: new ServiceName($record->name),
            description: $record->description === null ? null : new ServiceDescription($record->description),
            sortOrder: new SortOrder($record->sortOrder),
            status: ServiceStatus::from($record->status),
            createdAt: $record->domainCreatedAt,
            updatedAt: $record->domainUpdatedAt,
            version: $record->version,
        );
    }
}
