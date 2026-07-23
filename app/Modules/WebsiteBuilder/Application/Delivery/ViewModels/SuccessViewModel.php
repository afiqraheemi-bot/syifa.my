<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

/**
 * Structurally has no `bookingId` property — the mistake ADR-027/029 name is
 * made a compile-time impossibility, not a discipline.
 */
final readonly class SuccessViewModel
{
    public function __construct(
        public string $reference,
        public string $statusLabel,
        public string $submittedAtLabel,
        public ?string $whatsAppUrl,
    ) {}
}
