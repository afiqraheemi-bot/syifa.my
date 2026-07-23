<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * The governed read contract ADR-027 deferred ("via a governed query, not
 * invented here") and ADR-031 defines. Sprint 1 binds a Fixture
 * implementation; a future Sprint binds a real adapter reading
 * `BookingFormConfiguration`, never exposing Doctor/Branch even though the
 * Domain object supports toggling them.
 */
interface PublicBookingFormConfigurationReaderInterface
{
    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormConfiguration;
}
