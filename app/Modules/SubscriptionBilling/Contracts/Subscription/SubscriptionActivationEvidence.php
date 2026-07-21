<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

final readonly class SubscriptionActivationEvidence
{
    public function __construct(
        public bool $authoritative,
        public string $paymentId,
        public string $paymentStatus,
        public ?string $paymentTenantId,
        public string $paymentCommercialOfferId,
        public string $paymentClinicRegistrationId,
        public int $paymentAmountMinor,
        public string $paymentCurrency,
        public ?string $offerId,
        public ?string $offerTenantId,
        public ?string $offerClinicRegistrationId,
        public ?string $offerClaimedPaymentId,
        public ?string $planOfferingId,
        public ?string $planId,
        public ?string $billingCycleId,
        public ?string $offerBillingPeriodStart,
        public ?string $offerBillingPeriodEnd,
        public ?int $offerAmountMinor,
        public ?string $offerCurrency,
        public ?string $offeringConfigurationVersion,
        public ?string $capabilityConfigurationReference,
        public ?string $clinicRegistrationId,
        public ?string $clinicTenantId,
    ) {}
}
