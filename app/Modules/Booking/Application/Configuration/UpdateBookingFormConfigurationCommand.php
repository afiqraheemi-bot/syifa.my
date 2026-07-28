<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Configuration;

final readonly class UpdateBookingFormConfigurationCommand
{
    /**
     * @param  list<string>  $fieldOrder
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public string $tenantId,
        public int $expectedVersion,
        public bool $serviceSelectionEnabled,
        public bool $serviceRequired,
        public bool $emailEnabled,
        public bool $emailRequired,
        public bool $notesEnabled,
        public bool $notesRequired,
        public array $fieldOrder,
        public array $labels,
    ) {}
}
