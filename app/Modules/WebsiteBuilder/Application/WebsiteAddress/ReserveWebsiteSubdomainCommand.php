<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAddress;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class ReserveWebsiteSubdomainCommand
{
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
        public string $addressId,
        public string $subdomain,
    ) {}
}
