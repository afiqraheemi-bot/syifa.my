<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

/**
 * The sole permitted path for anything outside the Booking Engine to read
 * Booking Form Configuration and the active Service catalogue — mirroring
 * `AvailableSlotReaderInterface`/`ClinicOperationalTimeReaderInterface`'s
 * existing pattern of a narrow, primitive-typed Contracts/Queries interface
 * rather than exposing `BookingFormConfigurationRepositoryInterface` or
 * `ServiceRepositoryInterface` directly. A Tenant with no configuration yet
 * resolves to the most conservative projection (every optional field
 * disabled, no services) rather than throwing.
 */
interface PublicBookingFormReaderInterface
{
    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormReaderData;
}
