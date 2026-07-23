<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

/** One Date or Time chip. `state` is one of "available"/"unavailable"/"unknown" (ADR-028). */
final readonly class AvailabilityChipViewData
{
    public function __construct(
        public string $value,
        public string $label,
        public string $state,
        public bool $selected,
    ) {}

    public function tappable(): bool
    {
        return $this->state === 'available';
    }
}
