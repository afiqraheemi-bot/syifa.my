<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;

/**
 * Narrow application boundary for the assignment-protected booking preview.
 * It reuses the same availability and submission gateways as public Booking,
 * without requiring a published Website render model or public hostname.
 */
final readonly class ProtectedBookingPreviewService
{
    public function __construct(
        private PublicAvailabilityReaderInterface $availability,
        private BookingSubmissionGatewayInterface $submissions,
        private PublicBookingFormConfigurationReaderInterface $formConfigurations,
    ) {}

    public function configuration(string $trustedTenantId): PublicBookingFormConfiguration
    {
        return $this->formConfigurations->forTrustedTenant($trustedTenantId);
    }

    /** @return list<PublicAvailabilitySlot> */
    public function availability(string $trustedTenantId, string $localDate): array
    {
        return $this->availability->forDate($trustedTenantId, $localDate);
    }

    /**
     * @throws PublicBookingValidationException
     * @throws PublicBookingBusinessRuleException
     * @throws PublicBookingAvailabilityException
     * @throws PublicBookingInfrastructureException
     */
    public function submit(string $trustedTenantId, BookingDraft $draft): BookingSuccessData
    {
        $result = $this->submissions->submit(new PublicBookingSubmission(
            tenantId: $trustedTenantId,
            patientName: (string) $draft->patientName,
            phone: (string) $draft->phone,
            appointmentDate: (string) $draft->appointmentDate,
            appointmentTime: (string) $draft->appointmentTime,
            consent: $draft->consent,
            serviceId: $draft->serviceId,
            email: $draft->email,
            notes: $draft->notes,
        ));

        return new BookingSuccessData($result->reference, $result->status, $result->submittedAt);
    }
}
