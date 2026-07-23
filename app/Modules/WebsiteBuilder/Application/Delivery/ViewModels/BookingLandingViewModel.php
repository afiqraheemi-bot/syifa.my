<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

/** Presentation-only. No Domain object, no Aggregate, no Active Model. */
final readonly class BookingLandingViewModel
{
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public ?string $whatsAppUrl,
    ) {}
}
