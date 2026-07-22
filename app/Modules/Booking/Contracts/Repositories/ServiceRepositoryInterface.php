<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Repositories;

use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

interface ServiceRepositoryInterface
{
    public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service;

    /** @return list<Service> */
    public function findAll(TenantId $tenantId): array;

    /** @return list<Service> */
    public function findActive(TenantId $tenantId): array;

    public function existsByName(TenantId $tenantId, string $name): bool;

    public function save(Service $service): void;
}
