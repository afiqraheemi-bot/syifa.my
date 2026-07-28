<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Provisioning;

final readonly class ProvisionedWebsiteFoundationData
{
    public function __construct(
        public string $tenantId,
        public string $clinicId,
        public string $websiteId,
    ) {}
}
