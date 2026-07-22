<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Repositories;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

interface BookingFormConfigurationRepositoryInterface
{
    public function findByTenant(TenantId $tenantId): ?BookingFormConfiguration;

    public function save(BookingFormConfiguration $configuration): void;
}
