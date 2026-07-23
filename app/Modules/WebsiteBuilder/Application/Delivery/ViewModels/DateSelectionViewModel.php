<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class DateSelectionViewModel
{
    /** @param list<AvailabilityChipViewData> $dates */
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public array $dates,
        public ?string $selectedDate,
    ) {}

    public function hasAnyAvailableDate(): bool
    {
        foreach ($this->dates as $date) {
            if ($date->tappable()) {
                return true;
            }
        }

        return false;
    }
}
