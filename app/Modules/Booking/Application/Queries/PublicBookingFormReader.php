<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Queries;

use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderData;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class PublicBookingFormReader implements PublicBookingFormReaderInterface
{
    public function __construct(
        private BookingFormConfigurationRepositoryInterface $formConfigurations,
        private ServiceRepositoryInterface $services,
    ) {}

    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormReaderData
    {
        $tenantId = new TenantId($trustedTenantId);
        $configuration = $this->formConfigurations->findByTenant($tenantId);

        if ($configuration === null) {
            return new PublicBookingFormReaderData(false, false, false, false, []);
        }

        $serviceSelectionEnabled = $configuration->isEnabled(BookingFormField::Service);

        return new PublicBookingFormReaderData(
            serviceSelectionEnabled: $serviceSelectionEnabled,
            serviceSelectionRequired: $configuration->requiredFields()->contains(BookingFormField::Service),
            emailEnabled: $configuration->isEnabled(BookingFormField::Email),
            notesEnabled: $configuration->isEnabled(BookingFormField::Notes),
            services: $serviceSelectionEnabled ? $this->activeServices($tenantId) : [],
        );
    }

    /** @return list<PublicBookingFormServiceData> */
    private function activeServices(TenantId $tenantId): array
    {
        return array_map(
            static fn (Service $service): PublicBookingFormServiceData => new PublicBookingFormServiceData(
                $service->id->value,
                $service->name->value,
            ),
            $this->services->findActive($tenantId),
        );
    }
}
