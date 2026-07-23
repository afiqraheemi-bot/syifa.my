<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class ServiceSelectionViewModel
{
    /** @param list<ServiceOptionViewData> $options */
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public array $options,
        public bool $serviceRequired,
    ) {}
}
