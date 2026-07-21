<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\EntitlementChanged;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionActivated;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionCancelled;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionCreated;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionExpired;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionReactivated;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionRenewalDue;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Events\SubscriptionSuspended;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionOfferingException;
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

final class Subscription
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        public readonly SubscriptionId $id,
        public readonly TenantId $tenantId,
        public readonly ClinicRegistrationId $clinicRegistrationId,
        public readonly PaymentId $paymentId,
        public readonly CommercialOfferId $commercialOfferId,
        private PlanId $planId,
        private BillingCycleId $billingCycleId,
        private Money $price,
        private BillingPeriod $billingPeriod,
        private Entitlement $entitlement,
        private SubscriptionStatus $status,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $lastChangedAt,
        private int $version,
    ) {}

    public static function create(
        SubscriptionId $id,
        TenantId $tenantId,
        ClinicRegistrationId $clinicRegistrationId,
        PaymentId $paymentId,
        CommercialOfferId $commercialOfferId,
        PlanId $planId,
        BillingCycleId $billingCycleId,
        Money $price,
        BillingPeriod $billingPeriod,
        Entitlement $entitlement,
        DateTimeImmutable $occurredAt,
    ): self {
        self::assertEntitlementMatches($planId, $billingCycleId, $entitlement);
        if ($entitlement->status !== EntitlementStatus::Pending) {
            throw new InvalidSubscriptionOfferingException('A new Subscription requires a pending Entitlement.');
        }

        $subscription = new self(
            $id,
            $tenantId,
            $clinicRegistrationId,
            $paymentId,
            $commercialOfferId,
            $planId,
            $billingCycleId,
            $price,
            $billingPeriod,
            $entitlement,
            SubscriptionStatus::Pending,
            $occurredAt,
            $occurredAt,
            0,
        );
        $subscription->record(new SubscriptionCreated(
            $id->value,
            $tenantId->value,
            $planId->value,
            $billingCycleId->value,
            $occurredAt,
        ));

        return $subscription;
    }

    public static function reconstitute(
        SubscriptionId $id,
        TenantId $tenantId,
        ClinicRegistrationId $clinicRegistrationId,
        PaymentId $paymentId,
        CommercialOfferId $commercialOfferId,
        PlanId $planId,
        BillingCycleId $billingCycleId,
        Money $price,
        BillingPeriod $billingPeriod,
        Entitlement $entitlement,
        SubscriptionStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $lastChangedAt,
        int $version,
    ): self {
        if ($version < 1) {
            throw new InvalidSubscriptionLifecycleTransitionException(
                'A persisted Subscription requires a positive aggregate version.',
            );
        }

        if ($lastChangedAt < $createdAt) {
            throw new InvalidSubscriptionLifecycleTransitionException(
                'A persisted Subscription cannot change before it was created.',
            );
        }

        self::assertEntitlementMatches($planId, $billingCycleId, $entitlement);
        self::assertLifecycleMatchesEntitlement($status, $entitlement);

        return new self(
            $id,
            $tenantId,
            $clinicRegistrationId,
            $paymentId,
            $commercialOfferId,
            $planId,
            $billingCycleId,
            $price,
            $billingPeriod,
            $entitlement,
            $status,
            $createdAt,
            $lastChangedAt,
            $version,
        );
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function billingCycleId(): BillingCycleId
    {
        return $this->billingCycleId;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function billingPeriod(): BillingPeriod
    {
        return $this->billingPeriod;
    }

    public function entitlement(): Entitlement
    {
        return $this->entitlement;
    }

    public function lastChangedAt(): DateTimeImmutable
    {
        return $this->lastChangedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizePersistenceVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidSubscriptionLifecycleTransitionException(
                'A Subscription persistence version must advance by exactly one.',
            );
        }

        $this->version = $version;
    }

    public function hasCapability(CapabilityKey $capability): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::Reactivated,
        ], true)
            && $this->entitlement->status === EntitlementStatus::Effective
            && $this->entitlement->has($capability);
    }

    public function activate(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([SubscriptionStatus::Pending, SubscriptionStatus::Reactivated], 'activate');
        $this->transitionTo(SubscriptionStatus::Active, $occurredAt);
        $this->replaceCommercialSnapshot(
            $this->planId,
            $this->billingCycleId,
            $this->price,
            $this->billingPeriod,
            $this->entitlement->withStatus(EntitlementStatus::Effective),
            $occurredAt,
        );
        $this->record(new SubscriptionActivated($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function changePlan(
        PlanId $planId,
        BillingCycleId $billingCycleId,
        Money $price,
        Entitlement $entitlement,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertStatusIn([
            SubscriptionStatus::Active,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Reactivated,
        ], 'change Plan');
        self::assertEntitlementMatches($planId, $billingCycleId, $entitlement);
        if ($entitlement->status !== EntitlementStatus::Effective) {
            throw new InvalidSubscriptionOfferingException('A Plan change requires an effective Entitlement.');
        }

        if ($this->planId->value === $planId->value
            && $this->billingCycleId->value === $billingCycleId->value
            && $this->price->equals($price)
            && $this->entitlement->equals($entitlement)) {
            throw new InvalidSubscriptionOfferingException('A Plan change must change the purchased offering.');
        }

        $this->replaceCommercialSnapshot(
            $planId,
            $billingCycleId,
            $price,
            $this->billingPeriod,
            $entitlement,
            $occurredAt,
        );
    }

    public function markRenewalDue(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([SubscriptionStatus::Active], 'mark renewal due');
        $this->transitionTo(SubscriptionStatus::RenewalDue, $occurredAt);
        $this->record(new SubscriptionRenewalDue($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function renew(
        BillingPeriod $billingPeriod,
        Money $price,
        Entitlement $entitlement,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertStatusIn([SubscriptionStatus::RenewalDue], 'renew');
        if (! $billingPeriod->immediatelyFollows($this->billingPeriod)) {
            throw new InvalidSubscriptionOfferingException(
                'A renewed Billing Period must begin exactly one calendar day after the current period ends.',
            );
        }
        self::assertEntitlementMatches($this->planId, $this->billingCycleId, $entitlement);
        if ($entitlement->status !== EntitlementStatus::Effective) {
            throw new InvalidSubscriptionOfferingException('Renewal requires an effective Entitlement.');
        }

        $this->transitionTo(SubscriptionStatus::Active, $occurredAt);
        $this->replaceCommercialSnapshot(
            $this->planId,
            $this->billingCycleId,
            $price,
            $billingPeriod,
            $entitlement,
            $occurredAt,
        );
        $this->record(new SubscriptionActivated($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([
            SubscriptionStatus::Active,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Restricted,
        ], 'cancel');
        $this->transitionTo(SubscriptionStatus::Cancelled, $occurredAt);
        $this->record(new SubscriptionCancelled($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([
            SubscriptionStatus::Active,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::Restricted,
        ], 'expire');
        if (! $this->billingPeriod->hasEndedBefore($occurredAt->format('Y-m-d'))) {
            throw new InvalidSubscriptionLifecycleTransitionException(
                'A Subscription cannot expire before its inclusive Billing Period ends.',
            );
        }
        $this->transitionTo(SubscriptionStatus::Expired, $occurredAt);
        $this->replaceCommercialSnapshot(
            $this->planId,
            $this->billingCycleId,
            $this->price,
            $this->billingPeriod,
            $this->entitlement->withStatus(EntitlementStatus::Expired),
            $occurredAt,
        );
        $this->record(new SubscriptionExpired($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function suspend(DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([
            SubscriptionStatus::Active,
            SubscriptionStatus::PaymentActionRequired,
            SubscriptionStatus::Restricted,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Reactivated,
        ], 'suspend');
        $this->transitionTo(SubscriptionStatus::Suspended, $occurredAt);
        $this->replaceCommercialSnapshot(
            $this->planId,
            $this->billingCycleId,
            $this->price,
            $this->billingPeriod,
            $this->entitlement->withStatus(EntitlementStatus::Suspended),
            $occurredAt,
        );
        $this->record(new SubscriptionSuspended($this->id->value, $this->tenantId->value, $occurredAt));
    }

    public function reactivate(Entitlement $entitlement, DateTimeImmutable $occurredAt): void
    {
        $this->assertStatusIn([
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::Expired,
            SubscriptionStatus::Suspended,
        ], 'reactivate');
        self::assertEntitlementMatches($this->planId, $this->billingCycleId, $entitlement);
        if ($entitlement->status !== EntitlementStatus::Effective) {
            throw new InvalidSubscriptionOfferingException('Reactivation requires an effective Entitlement.');
        }

        $this->transitionTo(SubscriptionStatus::Reactivated, $occurredAt);
        $this->replaceCommercialSnapshot(
            $this->planId,
            $this->billingCycleId,
            $this->price,
            $this->billingPeriod,
            $entitlement,
            $occurredAt,
        );
        $this->record(new SubscriptionReactivated($this->id->value, $this->tenantId->value, $occurredAt));
    }

    /** @return list<object> */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function transitionTo(SubscriptionStatus $status, DateTimeImmutable $occurredAt): void
    {
        $this->assertOccurredAt($occurredAt);
        $this->status = $status;
        $this->lastChangedAt = $occurredAt;
    }

    private function replaceCommercialSnapshot(
        PlanId $planId,
        BillingCycleId $billingCycleId,
        Money $price,
        BillingPeriod $billingPeriod,
        Entitlement $entitlement,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->assertOccurredAt($occurredAt);

        $event = new EntitlementChanged(
            $this->id->value,
            $this->tenantId->value,
            $this->planId->value,
            $this->billingCycleId->value,
            $this->price->amountMinor,
            $this->price->currencyCode,
            $this->billingPeriod->startsOn,
            $this->billingPeriod->endsOn,
            $this->entitlement->configurationVersion,
            $this->entitlement->status->value,
            $planId->value,
            $billingCycleId->value,
            $price->amountMinor,
            $price->currencyCode,
            $billingPeriod->startsOn,
            $billingPeriod->endsOn,
            $entitlement->configurationVersion,
            $entitlement->status->value,
            $occurredAt,
        );

        $this->planId = $planId;
        $this->billingCycleId = $billingCycleId;
        $this->price = $price;
        $this->billingPeriod = $billingPeriod;
        $this->entitlement = $entitlement;
        $this->lastChangedAt = $occurredAt;
        $this->record($event);
    }

    /** @param list<SubscriptionStatus> $allowed */
    private function assertStatusIn(array $allowed, string $action): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw new InvalidSubscriptionLifecycleTransitionException(sprintf(
                'A Subscription cannot %s while it is %s.',
                $action,
                $this->status->value,
            ));
        }
    }

    private function assertOccurredAt(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt < $this->lastChangedAt) {
            throw new InvalidSubscriptionLifecycleTransitionException(
                'A Subscription transition cannot precede its current lifecycle state.',
            );
        }
    }

    private static function assertEntitlementMatches(
        PlanId $planId,
        BillingCycleId $billingCycleId,
        Entitlement $entitlement,
    ): void {
        if ($entitlement->planId->value !== $planId->value
            || $entitlement->billingCycleId->value !== $billingCycleId->value) {
            throw new InvalidSubscriptionOfferingException(
                'Entitlement must be derived from the Subscription Plan and Billing Cycle.',
            );
        }
    }

    private static function assertLifecycleMatchesEntitlement(
        SubscriptionStatus $status,
        Entitlement $entitlement,
    ): void {
        $requiredStatus = match ($status) {
            SubscriptionStatus::Pending => EntitlementStatus::Pending,
            SubscriptionStatus::Active,
            SubscriptionStatus::RenewalDue,
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::Reactivated => EntitlementStatus::Effective,
            SubscriptionStatus::Expired => EntitlementStatus::Expired,
            SubscriptionStatus::Suspended => EntitlementStatus::Suspended,
            SubscriptionStatus::PaymentActionRequired,
            SubscriptionStatus::Restricted => null,
        };

        if ($requiredStatus !== null && $entitlement->status !== $requiredStatus) {
            throw new InvalidSubscriptionOfferingException(
                'Persisted Subscription lifecycle and Entitlement status are inconsistent.',
            );
        }
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
