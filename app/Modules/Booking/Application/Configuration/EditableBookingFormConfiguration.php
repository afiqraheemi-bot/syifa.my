<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Configuration;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;

final readonly class EditableBookingFormConfiguration
{
    /** @param list<Service> $activeServices */
    public function __construct(
        private BookingFormConfiguration $configuration,
        private array $activeServices,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $required = $this->configuration->requiredFields();

        return [
            'version' => $this->configuration->version(),
            'service_selection_enabled' => $this->configuration->isEnabled(BookingFormField::Service),
            'service_required' => $required->contains(BookingFormField::Service),
            'email_enabled' => $this->configuration->isEnabled(BookingFormField::Email),
            'email_required' => $required->contains(BookingFormField::Email),
            'notes_enabled' => $this->configuration->isEnabled(BookingFormField::Notes),
            'notes_required' => $required->contains(BookingFormField::Notes),
            'field_order' => $this->configuration->fieldOrder()->values(),
            'labels' => $this->configuration->fieldLabels()->labels,
            'active_services' => array_map(
                static fn (Service $service): array => [
                    'id' => $service->id->value,
                    'name' => $service->name->value,
                ],
                $this->activeServices,
            ),
        ];
    }
}
