<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Provisioning;

use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use DateTimeImmutable;

interface ReserveProvisionedWebsiteAddressInterface
{
    public function execute(
        string $addressId,
        string $tenantId,
        string $websiteId,
        string $clinicName,
        DateTimeImmutable $occurredAt,
        ?string $preferredSubdomain = null,
    ): WebsitePublicAddressData;
}
