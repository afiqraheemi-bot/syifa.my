<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\Exceptions\TransientSubscriptionActivationException;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplication;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidenceRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionOfferingException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingCycleId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingPeriod;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\ClinicRegistrationId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CommercialOfferId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Entitlement;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\EntitlementStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class ActivateSubscriptionFromVerifiedPaymentService
{
    public function __construct(
        private SubscriptionActivationApplicationRepositoryInterface $applications,
        private SubscriptionActivationEvidenceRepositoryInterface $evidence,
        private SubscriptionRepositoryInterface $subscriptions,
        private SubscriptionEntitlementComputationInterface $entitlements,
        private SubscriptionActivationReconciliationCaseRepositoryInterface $reconciliations,
        private SubscriptionActivationAuditInterface $audit,
        private SubscriptionActivationTransactionInterface $transactions,
        private SubscriptionIntegrationOutboxRepositoryInterface $outbox,
        private AnnualTermCalculator $terms,
        private SubscriptionActivationRetryPolicy $retry,
    ) {}

    public function register(string $sourceEventId, string $paymentId, string $tenantId, DateTimeImmutable $receivedAt): SubscriptionActivationApplication
    {
        return $this->applications->register($sourceEventId, $paymentId, $tenantId, $receivedAt);
    }

    public function execute(string $applicationId, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable;
        $claim = $this->applications->claim($applicationId, $now, $this->retry->leaseSeconds);
        if ($claim === null || $claim->claimToken === null) {
            return;
        }

        $this->transactions->run(function () use ($claim, $now): void {
            $evidence = $this->evidence->loadForUpdate($claim->sourceEventId, $claim->paymentId);
            if ($evidence === null || ! $evidence->authoritative || $evidence->paymentStatus !== 'succeeded') {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::InvalidEvidence, 'subscription.activation.quarantined', $now);

                return;
            }
            if ($evidence->paymentTenantId === null || $evidence->offerId === null || $evidence->clinicRegistrationId === null) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::InvalidEvidence, 'subscription.activation.quarantined', $now);

                return;
            }
            if ($evidence->paymentCommercialOfferId !== $evidence->offerId || $evidence->offerClaimedPaymentId !== $evidence->paymentId) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::CommercialOfferMismatch, 'subscription.activation.quarantined', $now);

                return;
            }
            if ($evidence->paymentTenantId !== $claim->tenantId || $evidence->offerTenantId !== $claim->tenantId
                || $evidence->clinicTenantId !== $claim->tenantId || $evidence->paymentClinicRegistrationId !== $evidence->clinicRegistrationId
                || $evidence->offerClinicRegistrationId !== $evidence->clinicRegistrationId) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::TenantMismatch, 'subscription.activation.quarantined', $now);

                return;
            }
            if ($evidence->offerAmountMinor !== $evidence->paymentAmountMinor || $evidence->offerCurrency !== $evidence->paymentCurrency) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::ObligationMismatch, 'subscription.activation.quarantined', $now);

                return;
            }

            $byPayment = $this->subscriptions->findByPaymentId(new PaymentId($evidence->paymentId));
            if ($byPayment !== null) {
                $this->finish($claim, SubscriptionActivationApplicationStatus::Applied, SubscriptionActivationApplicationResultCode::AlreadyReflected, $now);

                return;
            }
            $byTenant = $this->subscriptions->findByTenantId(new TenantId($claim->tenantId));
            if ($byTenant !== null) {
                if (in_array($byTenant->status(), [SubscriptionStatus::RenewalDue, SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::Suspended, SubscriptionStatus::Reactivated], true)) {
                    $this->reconciliations->open($claim->id, $claim->paymentId, $claim->tenantId, 'existing_subscription_target', $now);
                    $this->terminal($claim, SubscriptionActivationApplicationStatus::ReconciliationRequired, SubscriptionActivationApplicationResultCode::ReconciliationRequired, 'subscription.activation.reconciliation_opened', $now);

                    return;
                }
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Ignored, SubscriptionActivationApplicationResultCode::Superseded, 'subscription.activation.ignored', $now);

                return;
            }

            if ($evidence->planOfferingId === null || $evidence->planId === null || $evidence->billingCycleId === null
                || $evidence->offerBillingPeriodStart === null || $evidence->offerBillingPeriodEnd === null
                || $evidence->offeringConfigurationVersion === null || $evidence->capabilityConfigurationReference === null) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::InvalidEvidence, 'subscription.activation.quarantined', $now);

                return;
            }
            $resolved = new ResolvedSubscriptionOfferingData(
                $evidence->planOfferingId, $evidence->planId, $evidence->billingCycleId, $evidence->paymentAmountMinor, $evidence->paymentCurrency,
                $evidence->offerBillingPeriodStart, $evidence->offerBillingPeriodEnd, $evidence->offeringConfigurationVersion, $evidence->capabilityConfigurationReference,
            );
            $computed = $this->entitlements->compute($resolved);
            if ($computed->planId !== $evidence->planId || $computed->billingOptionId !== $evidence->billingCycleId) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::InvalidEvidence, 'subscription.activation.quarantined', $now);

                return;
            }
            try {
                $planId = new PlanId($evidence->planId);
                $billingCycleId = new BillingCycleId($evidence->billingCycleId);
                $term = $this->terms->calculate($now);
                $subscription = Subscription::create(
                    new SubscriptionId($claim->subscriptionId), new TenantId($claim->tenantId),
                    new ClinicRegistrationId($evidence->clinicRegistrationId), new PaymentId($evidence->paymentId),
                    new CommercialOfferId($evidence->offerId), $planId, $billingCycleId,
                    new Money($evidence->paymentAmountMinor, $evidence->paymentCurrency),
                    new BillingPeriod($term['starts_on'], $term['ends_on']),
                    new Entitlement($planId, $billingCycleId, $computed->configurationVersion, EntitlementStatus::Pending, array_map(static fn (string $key): CapabilityKey => new CapabilityKey($key), $computed->capabilityKeys)),
                    $now,
                );
                $subscription->activate($now);
            } catch (InvalidSubscriptionLifecycleTransitionException|InvalidSubscriptionOfferingException|InvalidSubscriptionValueException) {
                $this->terminal($claim, SubscriptionActivationApplicationStatus::Quarantined, SubscriptionActivationApplicationResultCode::InvalidEvidence, 'subscription.activation.quarantined', $now);

                return;
            }
            $this->subscriptions->save($subscription);
            $this->outbox->add(new SubscriptionActivatedIntegrationEvent(
                eventId: $this->eventId($claim->id), subscriptionId: $claim->subscriptionId, tenantId: $claim->tenantId,
                clinicRegistrationId: $evidence->clinicRegistrationId, paymentId: $claim->paymentId,
                commercialOfferId: $evidence->offerId, planId: $evidence->planId, billingCycleId: $evidence->billingCycleId,
                startsOn: $term['starts_on'], endsOn: $term['ends_on'], occurredAt: $now,
            ));
            $this->finish($claim, SubscriptionActivationApplicationStatus::Applied, SubscriptionActivationApplicationResultCode::Applied, $now);
            $this->audit->record('subscription.activation.applied', $claim->id, $claim->subscriptionId, $claim->paymentId, $claim->tenantId, SubscriptionActivationApplicationResultCode::Applied, $now);
        });
    }

    private function terminal(SubscriptionActivationApplication $claim, SubscriptionActivationApplicationStatus $status, SubscriptionActivationApplicationResultCode $code, string $action, DateTimeImmutable $now): void
    {
        $this->finish($claim, $status, $code, $now);
        $this->audit->record($action, $claim->id, $claim->subscriptionId, $claim->paymentId, $claim->tenantId, $code, $now);
    }

    private function finish(SubscriptionActivationApplication $claim, SubscriptionActivationApplicationStatus $status, SubscriptionActivationApplicationResultCode $code, DateTimeImmutable $now): void
    {
        if ($claim->claimToken === null || ! $this->applications->complete($claim->id, $claim->claimToken, $status, $code, $now)) {
            throw new TransientSubscriptionActivationException('Subscription activation claim became stale.');
        }
    }

    private function eventId(string $applicationId): string
    {
        $hex = substr(hash('sha256', $applicationId.'|'.SubscriptionActivatedIntegrationEvent::TYPE), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
