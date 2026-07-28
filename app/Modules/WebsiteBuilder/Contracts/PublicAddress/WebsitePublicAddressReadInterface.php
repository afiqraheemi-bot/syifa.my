<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicAddress;

interface WebsitePublicAddressReadInterface
{
    public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData;

    public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData;

    public function resolveActiveHost(string $host): ?WebsitePublicAddressData;
}
