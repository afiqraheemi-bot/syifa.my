<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Queries;

use App\Modules\Booking\Contracts\Queries\ActiveServiceReferenceReaderInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class ActiveServiceReferenceReader implements ActiveServiceReferenceReaderInterface
{
    public function __construct(private ServiceRepositoryInterface $services) {}

    public function forTenant(string $tenantId): array
    {
        return array_map(
            static fn (Service $service): string => $service->id->value,
            $this->services->findActive(new TenantId($tenantId)),
        );
    }
}
