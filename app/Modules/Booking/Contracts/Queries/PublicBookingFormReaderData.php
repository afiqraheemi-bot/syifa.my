<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

/**
 * The narrow, public-safe projection of `BookingFormConfiguration` plus the
 * active Service catalogue (ADR-027/031's deferred "governed query", now
 * defined). `Doctor` and `Branch` configuration are never read, never
 * mapped, and have no property on this type at all — ADR-013 locks Doctor
 * out of MVP scope and ADR-027 never adopted Branch into the public
 * contract.
 */
final readonly class PublicBookingFormReaderData
{
    /** @param list<PublicBookingFormServiceData> $services */
    public function __construct(
        public bool $serviceSelectionEnabled,
        public bool $serviceSelectionRequired,
        public bool $emailEnabled,
        public bool $notesEnabled,
        public array $services,
    ) {}
}
