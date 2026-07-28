<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\Booking\Contracts\Queries\ActiveServiceReferenceReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;

final readonly class BookingActiveServiceReferenceAdapter implements ActiveServiceReferenceReadInterface
{
    public function __construct(private ActiveServiceReferenceReaderInterface $services) {}

    public function forTenant(string $tenantId): array
    {
        return $this->services->forTenant($tenantId);
    }
}
