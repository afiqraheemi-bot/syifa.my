<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface TransactionalNotificationGatewayInterface
{
    public function bookingReceived(
        string $tenantId,
        string $bookingId,
        string $bookingReference,
        ?string $patientEmail,
    ): void;

    public function bookingChanged(
        string $tenantId,
        string $bookingId,
        string $bookingReference,
        ?string $patientEmail,
        string $change,
    ): void;

    public function designerAssigned(
        string $tenantId,
        string $onboardingJobId,
        string $platformIdentityId,
    ): void;

    public function websitePublished(string $tenantId, string $websiteId): void;

    public function websiteReviewRequested(string $tenantId, string $onboardingJobId): void;

    public function subscriptionActivated(
        string $tenantId,
        string $subscriptionId,
        string $clinicRegistrationId,
    ): void;
}
