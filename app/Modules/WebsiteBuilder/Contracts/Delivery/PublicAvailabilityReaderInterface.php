<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * The sole permitted path from Delivery into the Booking Engine's Availability
 * signal (ADR-028), wrapping the internal `AvailableSlotReaderInterface`.
 * Read-only and advisory — never authoritative (ADR-027/028's Booking
 * submission remains the sole authority). Sprint 1 binds a Fixture
 * implementation; a future Sprint binds a real adapter over
 * `AvailableSlotReaderInterface`, with the same short-lived,
 * per-Tenant-per-date caching ADR-028 requires.
 */
interface PublicAvailabilityReaderInterface
{
    /** @return list<PublicAvailabilitySlot> */
    public function forDate(string $trustedTenantId, string $localDate): array;
}
