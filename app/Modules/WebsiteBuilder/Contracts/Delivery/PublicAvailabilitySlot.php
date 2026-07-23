<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * One slot of the ADR-028 public Availability projection. Carries only what
 * ADR-028 permits — a local date/time/timezone and the closed three-state
 * vocabulary — never capacity, reservation, or scheduling-configuration detail.
 */
final readonly class PublicAvailabilitySlot
{
    public function __construct(
        public string $localDate,
        public string $localStart,
        public string $localEnd,
        public string $timezone,
        public PublicAvailabilityState $state,
    ) {}
}
