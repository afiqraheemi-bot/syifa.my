<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class TimeSelectionViewModel
{
    /** @param list<AvailabilityChipViewData> $times */
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public string $selectedDate,
        public array $times,
        public ?string $selectedTime,
    ) {}

    public function hasNoAvailableTimes(): bool
    {
        foreach ($this->times as $time) {
            if ($time->tappable()) {
                return false;
            }
        }

        return true;
    }
}
