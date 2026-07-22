<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Repositories;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;

interface ClinicRepositoryInterface
{
    public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic;

    public function findByTenantId(TenantId $tenantId): ?Clinic;

    public function save(Clinic $clinic): void;
}
