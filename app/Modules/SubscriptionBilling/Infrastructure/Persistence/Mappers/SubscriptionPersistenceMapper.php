<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

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
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionStorageRecord;

final class SubscriptionPersistenceMapper
{
    public function toRecord(Subscription $subscription): SubscriptionStorageRecord
    {
        return new SubscriptionStorageRecord(
            id: $subscription->id->value,
            tenantId: $subscription->tenantId->value,
            clinicRegistrationId: $subscription->clinicRegistrationId->value,
            paymentId: $subscription->paymentId->value,
            commercialOfferId: $subscription->commercialOfferId->value,
            planId: $subscription->planId()->value,
            billingCycleId: $subscription->billingCycleId()->value,
            amountMinor: $subscription->price()->amountMinor,
            currency: $subscription->price()->currencyCode,
            startsOn: $subscription->billingPeriod()->startsOn,
            endsOn: $subscription->billingPeriod()->endsOn,
            status: $subscription->status()->value,
            entitlementConfigurationVersion: $subscription->entitlement()->configurationVersion,
            entitlementStatus: $subscription->entitlement()->status->value,
            entitlementCapabilities: array_map(
                static fn (CapabilityKey $capability): string => $capability->value,
                $subscription->entitlement()->capabilities,
            ),
            createdAt: $subscription->createdAt,
            lastChangedAt: $subscription->lastChangedAt(),
            version: $subscription->version(),
        );
    }

    public function toDomain(SubscriptionStorageRecord $record): Subscription
    {
        $planId = new PlanId($record->planId);
        $billingCycleId = new BillingCycleId($record->billingCycleId);

        return Subscription::reconstitute(
            id: new SubscriptionId($record->id),
            tenantId: new TenantId($record->tenantId),
            clinicRegistrationId: new ClinicRegistrationId($record->clinicRegistrationId),
            paymentId: new PaymentId($record->paymentId),
            commercialOfferId: new CommercialOfferId($record->commercialOfferId),
            planId: $planId,
            billingCycleId: $billingCycleId,
            price: new Money($record->amountMinor, $record->currency),
            billingPeriod: new BillingPeriod($record->startsOn, $record->endsOn),
            entitlement: new Entitlement(
                $planId,
                $billingCycleId,
                $record->entitlementConfigurationVersion,
                EntitlementStatus::from($record->entitlementStatus),
                array_map(static fn (string $capability): CapabilityKey => new CapabilityKey($capability), $record->entitlementCapabilities),
            ),
            status: SubscriptionStatus::from($record->status),
            createdAt: $record->createdAt,
            lastChangedAt: $record->lastChangedAt,
            version: $record->version,
        );
    }
}
