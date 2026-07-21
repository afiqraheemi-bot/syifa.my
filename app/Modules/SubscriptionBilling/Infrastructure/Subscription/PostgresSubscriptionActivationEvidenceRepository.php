<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidence;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidenceRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresSubscriptionActivationEvidenceRepository implements SubscriptionActivationEvidenceRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function loadForUpdate(string $sourceEventId, string $paymentId): ?SubscriptionActivationEvidence
    {
        $payment = $this->connection->table('payments')->where('id', $paymentId)->lockForUpdate()->first();
        if ($payment === null) {
            return null;
        }
        $clinic = $this->connection->table('clinic_registrations')->where('id', (string) $payment->clinic_registration_id)->lockForUpdate()->first();
        $offer = $this->connection->table('commercial_offers')->where('id', (string) $payment->commercial_offer_id)->first();
        $event = $this->connection->table('payment_integration_outbox')->where('id', $sourceEventId)
            ->where('payment_id', $paymentId)->where('event_type', 'VerifiedPaymentSucceeded')->where('event_version', 1)->first();

        return new SubscriptionActivationEvidence(
            authoritative: $event !== null, paymentId: (string) $payment->id, paymentStatus: (string) $payment->status,
            paymentTenantId: isset($payment->tenant_id) ? (string) $payment->tenant_id : null,
            paymentCommercialOfferId: (string) $payment->commercial_offer_id, paymentClinicRegistrationId: (string) $payment->clinic_registration_id,
            paymentAmountMinor: (int) $payment->amount_minor, paymentCurrency: (string) $payment->currency,
            offerId: isset($offer->id) ? (string) $offer->id : null, offerTenantId: isset($offer->tenant_id) ? (string) $offer->tenant_id : null,
            offerClinicRegistrationId: isset($offer->clinic_registration_id) ? (string) $offer->clinic_registration_id : null,
            offerClaimedPaymentId: isset($offer->claimed_payment_id) ? (string) $offer->claimed_payment_id : null,
            planOfferingId: isset($offer->plan_offering_id) ? (string) $offer->plan_offering_id : null,
            planId: isset($offer->plan_id) ? (string) $offer->plan_id : null, billingCycleId: isset($offer->billing_cycle_id) ? (string) $offer->billing_cycle_id : null,
            offerBillingPeriodStart: isset($offer->billing_period_start) ? (string) $offer->billing_period_start : null,
            offerBillingPeriodEnd: isset($offer->billing_period_end) ? (string) $offer->billing_period_end : null,
            offerAmountMinor: isset($offer->total_amount_minor) ? (int) $offer->total_amount_minor : null,
            offerCurrency: isset($offer->currency) ? (string) $offer->currency : null,
            offeringConfigurationVersion: isset($offer->offering_configuration_version) ? (string) $offer->offering_configuration_version : null,
            capabilityConfigurationReference: isset($offer->capability_configuration_reference) ? (string) $offer->capability_configuration_reference : null,
            clinicRegistrationId: isset($clinic->id) ? (string) $clinic->id : null,
            clinicTenantId: isset($clinic->reserved_tenant_id) ? (string) $clinic->reserved_tenant_id : null,
        );
    }
}
