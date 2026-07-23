<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * ADR-031's narrow, defense-in-depth projection: only what the public contract
 * ever permits. No `doctor`, `branch`, `fieldOrder`, or `fieldLabels` property
 * exists on this type at all — the public field order is platform-frozen
 * (UI Specification), never Clinic-configurable.
 *
 * `services` is a Sprint 1 addition beyond ADR-031's original four booleans:
 * Service Selection cannot function without knowing which services exist, and
 * the real Service catalogue is explicitly out of this sprint's fixture scope
 * — carrying it here, on the same interface, avoids inventing a second one.
 */
final readonly class PublicBookingFormConfiguration
{
    /** @param list<PublicBookingServiceOption> $services */
    public function __construct(
        public bool $serviceSelectionEnabled,
        public bool $serviceSelectionRequired,
        public bool $emailEnabled,
        public bool $notesEnabled,
        public array $services,
    ) {}
}
