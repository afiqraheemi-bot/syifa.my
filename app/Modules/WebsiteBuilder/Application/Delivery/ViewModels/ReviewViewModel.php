<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class ReviewViewModel
{
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public ?string $serviceLabel,
        public string $date,
        public string $time,
        public string $patientName,
        public string $phone,
        public ?string $email,
        public ?string $notes,
    ) {}
}
