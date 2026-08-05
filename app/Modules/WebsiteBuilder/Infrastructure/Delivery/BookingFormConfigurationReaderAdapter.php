<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingServiceOption;

/**
 * Sprint 2 (Phase 2): the real adapter behind
 * `PublicBookingFormConfigurationReaderInterface` (ADR-027/031's deferred
 * "governed query"), replacing the Sprint 1 Fixture. A thin translation over
 * Booking's own `PublicBookingFormReaderInterface` — the narrow,
 * primitive-typed Contracts/Queries interface Booking exposes for exactly
 * this purpose (mirroring `AvailableSlotReaderInterface`'s established
 * pattern), never Booking's raw Repository interfaces directly.
 *
 * `featured` is always `false`: Booking's own Service catalogue has no such
 * concept (that is the Website's own marketing-page presentation authority,
 * `ServicePresentationItem`/`isFeatured` — a deliberately separate concern
 * this adapter does not read, to avoid coupling Booking's Service catalogue
 * to the Website's published marketing snapshot).
 */
final readonly class BookingFormConfigurationReaderAdapter implements PublicBookingFormConfigurationReaderInterface
{
    public function __construct(
        private PublicBookingFormReaderInterface $reader,
        private ActiveServiceCatalogueReaderInterface $services,
    ) {}

    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormConfiguration
    {
        $data = $this->reader->forTrustedTenant($trustedTenantId);

        return new PublicBookingFormConfiguration(
            serviceSelectionEnabled: $data->serviceSelectionEnabled,
            serviceSelectionRequired: $data->serviceSelectionRequired,
            emailEnabled: $data->emailEnabled,
            notesEnabled: $data->notesEnabled,
            services: array_map(
                static fn (PublicBookingFormServiceData $service): PublicBookingServiceOption => new PublicBookingServiceOption(
                    $service->id,
                    $service->name,
                    false,
                ),
                $this->services->forTenant($trustedTenantId),
            ),
        );
    }
}
