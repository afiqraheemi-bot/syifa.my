<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * Closes the gap ADR-029 identified: `PublicSiteContext` carries a trusted
 * `websiteId`, not the trusted Tenant identifier every Booking Contract call
 * requires. This is the one, sole permitted resolution step between the two.
 */
interface WebsiteTenantResolverInterface
{
    /** @throws WebsiteTenantNotFoundException when the Website identity is unknown */
    public function forTrustedWebsite(string $trustedWebsiteId): string;
}
