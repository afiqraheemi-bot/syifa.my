<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\Aggregates\Subscription;

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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SubscriptionTest extends TestCase
{
    public function test_creation_records_the_configured_offering_as_a_pending_tenant_owned_subscription(): void
    {
        $subscription = $this->subscription();

        self::assertSame(SubscriptionStatus::Pending, $subscription->status());
        self::assertSame($this->uuid(3), $subscription->planId()->value);
        self::assertSame($this->uuid(4), $subscription->billingCycleId()->value);
        self::assertSame(12500, $subscription->price()->amountMinor);
        self::assertSame('MYR', $subscription->price()->currencyCode);
        self::assertSame($this->uuid(2), $subscription->tenantId->value);
        self::assertSame($this->uuid(5), $subscription->clinicRegistrationId->value);
        self::assertSame($this->uuid(6), $subscription->paymentId->value);
        self::assertSame($this->uuid(7), $subscription->commercialOfferId->value);
        self::assertSame(0, $subscription->version());
        self::assertFalse($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $events = $subscription->releaseDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SubscriptionCreated::class, $events[0]);
        self::assertSame($this->uuid(1), $events[0]->subscriptionId);
        self::assertSame($this->uuid(2), $events[0]->tenantId);
        self::assertSame($this->uuid(3), $events[0]->planId);
        self::assertSame($this->uuid(4), $events[0]->billingCycleId);
        self::assertEquals($this->time(), $events[0]->occurredAt);
        self::assertSame([], $subscription->releaseDomainEvents());
    }

    public function test_activation_makes_only_the_configured_entitlement_effective(): void
    {
        $subscription = $this->subscription();
        $subscription->releaseDomainEvents();
        $subscription->activate($this->time('+1 minute'));

        self::assertSame(SubscriptionStatus::Active, $subscription->status());
        self::assertTrue($subscription->hasCapability(new CapabilityKey('configured.capability')));
        self::assertFalse($subscription->hasCapability(new CapabilityKey('not.in.configuration')));
        self::assertSame(EntitlementStatus::Effective, $subscription->entitlement()->status);
        $events = $subscription->releaseDomainEvents();
        $activated = array_values(array_filter($events, static fn (object $event): bool => $event instanceof SubscriptionActivated));
        self::assertCount(1, $activated);
        self::assertSame($this->uuid(1), $activated[0]->subscriptionId);
        self::assertSame($this->uuid(2), $activated[0]->tenantId);
        self::assertEquals($this->time('+1 minute'), $activated[0]->occurredAt);
    }

    public function test_plan_and_billing_cycle_change_are_atomic_with_price_and_entitlement(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->releaseDomainEvents();
        $plan = new PlanId($this->uuid(30));
        $cycle = new BillingCycleId($this->uuid(40));
        $entitlement = $this->entitlement($plan, $cycle, EntitlementStatus::Effective, 'catalogue-v2', [
            'configured.new-capability',
        ]);

        $subscription->changePlan($plan, $cycle, new Money(23000, 'MYR'), $entitlement, $this->time('+2 minutes'));

        self::assertSame($plan, $subscription->planId());
        self::assertSame($cycle, $subscription->billingCycleId());
        self::assertSame(23000, $subscription->price()->amountMinor);
        self::assertTrue($subscription->hasCapability(new CapabilityKey('configured.new-capability')));
        self::assertFalse($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $events = $subscription->releaseDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(EntitlementChanged::class, $events[0]);
        self::assertSame($this->uuid(1), $events[0]->subscriptionId);
        self::assertSame($this->uuid(2), $events[0]->tenantId);
        self::assertSame($this->uuid(3), $events[0]->previousPlanId);
        self::assertSame($plan->value, $events[0]->newPlanId);
        self::assertSame($this->uuid(4), $events[0]->previousBillingCycleId);
        self::assertSame($cycle->value, $events[0]->newBillingCycleId);
        self::assertSame(12500, $events[0]->previousAmountMinor);
        self::assertSame(23000, $events[0]->newAmountMinor);
        self::assertSame('catalogue-v1', $events[0]->previousConfigurationVersion);
        self::assertSame('catalogue-v2', $events[0]->newConfigurationVersion);
        self::assertEquals($this->time('+2 minutes'), $events[0]->occurredAt);
    }

    public function test_entitlement_must_match_the_purchased_plan_and_billing_cycle(): void
    {
        $this->expectException(InvalidSubscriptionOfferingException::class);

        Subscription::create(
            new SubscriptionId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicRegistrationId($this->uuid(5)),
            new PaymentId($this->uuid(6)),
            new CommercialOfferId($this->uuid(7)),
            new PlanId($this->uuid(3)),
            new BillingCycleId($this->uuid(4)),
            new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-01', '2026-07-31'),
            $this->entitlement(
                new PlanId($this->uuid(99)),
                new BillingCycleId($this->uuid(4)),
                EntitlementStatus::Pending,
            ),
            $this->time(),
        );
    }

    public function test_renewal_replaces_the_non_overlapping_period_and_current_price_snapshot(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->markRenewalDue($this->time('+2 minutes'));
        $subscription->releaseDomainEvents();

        $subscription->renew(
            new BillingPeriod('2026-08-01', '2026-09-01'),
            new Money(13000, 'MYR'),
            $this->entitlement(
                $subscription->planId(),
                $subscription->billingCycleId(),
                EntitlementStatus::Effective,
                'catalogue-v2',
            ),
            $this->time('+3 minutes'),
        );

        self::assertSame(SubscriptionStatus::Active, $subscription->status());
        self::assertSame('2026-08-01', $subscription->billingPeriod()->startsOn);
        self::assertSame(13000, $subscription->price()->amountMinor);
        $activated = array_values(array_filter(
            $subscription->releaseDomainEvents(),
            static fn (object $event): bool => $event instanceof SubscriptionActivated,
        ));
        self::assertCount(1, $activated);
        self::assertSame($this->uuid(1), $activated[0]->subscriptionId);
        self::assertSame($this->uuid(2), $activated[0]->tenantId);
        self::assertEquals($this->time('+3 minutes'), $activated[0]->occurredAt);
    }

    public function test_renewal_due_event_contains_identity_and_occurrence_time(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->releaseDomainEvents();
        $occurredAt = $this->time('+2 minutes');

        $subscription->markRenewalDue($occurredAt);

        $events = $subscription->releaseDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SubscriptionRenewalDue::class, $events[0]);
        self::assertSame($this->uuid(1), $events[0]->subscriptionId);
        self::assertSame($this->uuid(2), $events[0]->tenantId);
        self::assertEquals($occurredAt, $events[0]->occurredAt);
    }

    public function test_overlapping_renewal_is_rejected(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->markRenewalDue($this->time('+2 minutes'));

        $this->expectException(InvalidSubscriptionOfferingException::class);
        $subscription->renew(
            new BillingPeriod('2026-07-31', '2026-09-01'),
            new Money(13000, 'MYR'),
            $subscription->entitlement(),
            $this->time('+3 minutes'),
        );
    }

    public function test_renewal_with_a_calendar_gap_is_rejected(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->markRenewalDue($this->time('+2 minutes'));

        $this->expectException(InvalidSubscriptionOfferingException::class);
        $subscription->renew(
            new BillingPeriod('2026-08-02', '2026-09-01'),
            new Money(13000, 'MYR'),
            $subscription->entitlement(),
            $this->time('+3 minutes'),
        );
    }

    public function test_cancellation_preserves_entitlement_until_expiry_and_expiry_disables_it_without_deletion(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->releaseDomainEvents();
        $subscription->cancel($this->time('+2 minutes'));

        self::assertSame(SubscriptionStatus::Cancelled, $subscription->status());
        self::assertSame(EntitlementStatus::Effective, $subscription->entitlement()->status);
        self::assertTrue($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $cancelEvents = $subscription->releaseDomainEvents();
        self::assertCount(1, $cancelEvents);
        self::assertInstanceOf(SubscriptionCancelled::class, $cancelEvents[0]);
        self::assertSame($this->uuid(1), $cancelEvents[0]->subscriptionId);
        self::assertSame($this->uuid(2), $cancelEvents[0]->tenantId);
        self::assertEquals($this->time('+2 minutes'), $cancelEvents[0]->occurredAt);
        $expiredAt = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
        $subscription->expire($expiredAt);

        self::assertSame(SubscriptionStatus::Expired, $subscription->status());
        self::assertSame(EntitlementStatus::Expired, $subscription->entitlement()->status);
        self::assertFalse($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $events = $subscription->releaseDomainEvents();
        $expired = array_values(array_filter($events, static fn (object $event): bool => $event instanceof SubscriptionExpired));
        self::assertCount(1, $expired);
        self::assertSame($this->uuid(1), $expired[0]->subscriptionId);
        self::assertSame($this->uuid(2), $expired[0]->tenantId);
        self::assertEquals($expiredAt, $expired[0]->occurredAt);
    }

    public function test_expiry_before_the_inclusive_billing_period_end_is_rejected(): void
    {
        $subscription = $this->activeSubscription();
        $subscription->cancel($this->time('+2 minutes'));

        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        $subscription->expire(new DateTimeImmutable('2026-07-31T23:59:59+00:00'));
    }

    public function test_suspension_and_reactivation_preserve_tenant_and_require_recomputed_entitlement(): void
    {
        $subscription = $this->activeSubscription();
        $tenantId = $subscription->tenantId;
        $subscription->suspend($this->time('+2 minutes'));

        self::assertSame(SubscriptionStatus::Suspended, $subscription->status());
        self::assertFalse($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $suspensionEvents = $subscription->releaseDomainEvents();
        $suspended = array_values(array_filter(
            $suspensionEvents,
            static fn (object $event): bool => $event instanceof SubscriptionSuspended,
        ));
        self::assertCount(1, $suspended);
        self::assertSame($this->uuid(1), $suspended[0]->subscriptionId);
        self::assertSame($this->uuid(2), $suspended[0]->tenantId);
        self::assertEquals($this->time('+2 minutes'), $suspended[0]->occurredAt);
        $subscription->reactivate(
            $this->entitlement(
                $subscription->planId(),
                $subscription->billingCycleId(),
                EntitlementStatus::Effective,
                'catalogue-v2',
            ),
            $this->time('+3 minutes'),
        );

        self::assertSame(SubscriptionStatus::Reactivated, $subscription->status());
        self::assertSame($tenantId, $subscription->tenantId);
        self::assertTrue($subscription->hasCapability(new CapabilityKey('configured.capability')));
        $events = $subscription->releaseDomainEvents();
        $reactivated = array_values(array_filter(
            $events,
            static fn (object $event): bool => $event instanceof SubscriptionReactivated,
        ));
        self::assertCount(1, $reactivated);
        self::assertSame($this->uuid(1), $reactivated[0]->subscriptionId);
        self::assertSame($this->uuid(2), $reactivated[0]->tenantId);
        self::assertEquals($this->time('+3 minutes'), $reactivated[0]->occurredAt);
    }

    public function test_invalid_and_backdated_lifecycle_transitions_are_rejected(): void
    {
        $subscription = $this->subscription();
        $subscription->activate($this->time('+2 minutes'));

        try {
            $subscription->activate($this->time('+3 minutes'));
            self::fail('Repeated activation should fail.');
        } catch (InvalidSubscriptionLifecycleTransitionException) {
            self::assertSame(SubscriptionStatus::Active, $subscription->status());
        }

        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        $subscription->cancel($this->time('+1 minute'));
    }

    #[DataProvider('invalidPendingTransitionProvider')]
    public function test_invalid_transitions_from_pending_are_rejected(callable $transition): void
    {
        $subscription = $this->subscription();

        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        $transition($subscription, $this);
    }

    /** @return iterable<string, array{callable(Subscription, self): void}> */
    public static function invalidPendingTransitionProvider(): iterable
    {
        yield 'change plan' => [static function (Subscription $subscription, self $test): void {
            $subscription->changePlan(
                new PlanId($test->uuid(30)),
                new BillingCycleId($test->uuid(40)),
                new Money(23000, 'MYR'),
                $test->entitlement(
                    new PlanId($test->uuid(30)),
                    new BillingCycleId($test->uuid(40)),
                    EntitlementStatus::Effective,
                ),
                $test->time('+1 minute'),
            );
        }];
        yield 'mark renewal due' => [static fn (Subscription $subscription, self $test) => $subscription->markRenewalDue($test->time('+1 minute'))];
        yield 'renew' => [static fn (Subscription $subscription, self $test) => $subscription->renew(
            new BillingPeriod('2026-08-01', '2026-08-31'),
            new Money(12500, 'MYR'),
            $test->entitlement($subscription->planId(), $subscription->billingCycleId(), EntitlementStatus::Effective),
            $test->time('+1 minute'),
        )];
        yield 'cancel' => [static fn (Subscription $subscription, self $test) => $subscription->cancel($test->time('+1 minute'))];
        yield 'expire' => [static fn (Subscription $subscription, self $test) => $subscription->expire($test->time('+1 minute'))];
        yield 'suspend' => [static fn (Subscription $subscription, self $test) => $subscription->suspend($test->time('+1 minute'))];
        yield 'reactivate' => [static fn (Subscription $subscription, self $test) => $subscription->reactivate(
            $test->entitlement($subscription->planId(), $subscription->billingCycleId(), EntitlementStatus::Effective),
            $test->time('+1 minute'),
        )];
    }

    public function test_billing_period_uses_inclusive_calendar_dates(): void
    {
        $period = new BillingPeriod('2026-07-14', '2026-07-14');

        self::assertSame('2026-07-14', $period->startsOn);
        self::assertSame('2026-07-14', $period->endsOn);
        self::assertTrue((new BillingPeriod('2026-07-15', '2026-07-15'))->immediatelyFollows($period));
        self::assertFalse((new BillingPeriod('2026-07-14', '2026-07-15'))->immediatelyFollows($period));
        self::assertFalse((new BillingPeriod('2026-07-16', '2026-07-16'))->immediatelyFollows($period));
    }

    public function test_reconstitution_restores_the_snapshot_without_recording_events(): void
    {
        $subscription = $this->reconstitutedSubscription(SubscriptionStatus::Active, EntitlementStatus::Effective, 7);

        self::assertSame(7, $subscription->version());
        self::assertSame(SubscriptionStatus::Active, $subscription->status());
        self::assertSame($this->uuid(3), $subscription->planId()->value);
        self::assertSame($this->uuid(4), $subscription->billingCycleId()->value);
        self::assertSame(12500, $subscription->price()->amountMinor);
        self::assertSame('2026-07-01', $subscription->billingPeriod()->startsOn);
        self::assertSame('catalogue-v1', $subscription->entitlement()->configurationVersion);
        self::assertSame([], $subscription->releaseDomainEvents());
    }

    public function test_persisted_aggregate_requires_a_positive_version(): void
    {
        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        $this->reconstitutedSubscription(SubscriptionStatus::Active, EntitlementStatus::Effective, 0);
    }

    public function test_reconstitution_rejects_inconsistent_lifecycle_and_entitlement_state(): void
    {
        $this->expectException(InvalidSubscriptionOfferingException::class);
        $this->reconstitutedSubscription(SubscriptionStatus::Expired, EntitlementStatus::Effective, 1);
    }

    public function test_reconstitution_rejects_a_change_before_creation(): void
    {
        $plan = new PlanId($this->uuid(3));
        $cycle = new BillingCycleId($this->uuid(4));

        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        Subscription::reconstitute(
            new SubscriptionId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicRegistrationId($this->uuid(5)),
            new PaymentId($this->uuid(6)),
            new CommercialOfferId($this->uuid(7)),
            $plan,
            $cycle,
            new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-01', '2026-07-31'),
            $this->entitlement($plan, $cycle, EntitlementStatus::Effective),
            SubscriptionStatus::Active,
            $this->time('+1 minute'),
            $this->time(),
            1,
        );
    }

    public function test_persistence_version_must_advance_exactly_one_step(): void
    {
        $subscription = $this->subscription();
        $subscription->synchronizePersistenceVersion(1);
        self::assertSame(1, $subscription->version());

        $this->expectException(InvalidSubscriptionLifecycleTransitionException::class);
        $subscription->synchronizePersistenceVersion(3);
    }

    public function test_payment_controlled_states_are_approved_but_have_no_public_transition_command(): void
    {
        self::assertSame('payment_action_required', SubscriptionStatus::PaymentActionRequired->value);
        self::assertSame('restricted', SubscriptionStatus::Restricted->value);

        foreach ((new ReflectionMethod(Subscription::class, 'reconstitute'))->getDeclaringClass()->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic()) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                self::assertNotSame(SubscriptionStatus::class, (string) $parameter->getType(), $method->getName());
            }
        }
    }

    public function test_uuid_identifiers_accept_versions_one_through_eight_and_preserve_case(): void
    {
        foreach (range(1, 8) as $version) {
            $value = sprintf('018F0F5A-7B6C-%d123-8ABC-0123456789AB', $version);
            self::assertSame($value, (new SubscriptionId($value))->value);
            self::assertSame($value, (new TenantId($value))->value);
            self::assertSame($value, (new PlanId($value))->value);
            self::assertSame($value, (new BillingCycleId($value))->value);
        }
    }

    public function test_uuid_version_seven_is_supported_and_malformed_uuid_is_rejected(): void
    {
        self::assertSame(
            '018f0f5a-7b6c-7123-8abc-0123456789ab',
            (new SubscriptionId('018f0f5a-7b6c-7123-8abc-0123456789ab'))->value,
        );

        $this->expectException(InvalidSubscriptionValueException::class);
        new SubscriptionId('018f0f5a-7b6c-7123-7abc-0123456789ab');
    }

    #[DataProvider('invalidValueProvider')]
    public function test_value_objects_reject_invalid_commercial_values(callable $operation): void
    {
        $this->expectException(InvalidSubscriptionValueException::class);
        $operation();
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidValueProvider(): iterable
    {
        yield 'negative money' => [static fn () => new Money(-1, 'MYR')];
        yield 'invalid currency' => [static fn () => new Money(1, 'myr')];
        yield 'invalid calendar date' => [static fn () => new BillingPeriod('2026-02-30', '2026-03-01')];
        yield 'reversed period' => [static fn () => new BillingPeriod('2026-07-02', '2026-07-01')];
        yield 'invalid capability key' => [static fn () => new CapabilityKey('Not Configured')];
        yield 'invalid identifier' => [static fn () => new SubscriptionId('not-a-uuid')];
        yield 'invalid clinic registration identifier' => [static fn () => new ClinicRegistrationId('not-a-uuid')];
        yield 'invalid payment identifier' => [static fn () => new PaymentId('not-a-uuid')];
        yield 'invalid commercial offer identifier' => [static fn () => new CommercialOfferId('not-a-uuid')];
    }

    private function activeSubscription(): Subscription
    {
        $subscription = $this->subscription();
        $subscription->activate($this->time('+1 minute'));

        return $subscription;
    }

    private function subscription(): Subscription
    {
        $plan = new PlanId($this->uuid(3));
        $cycle = new BillingCycleId($this->uuid(4));

        return Subscription::create(
            new SubscriptionId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicRegistrationId($this->uuid(5)),
            new PaymentId($this->uuid(6)),
            new CommercialOfferId($this->uuid(7)),
            $plan,
            $cycle,
            new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-01', '2026-07-31'),
            $this->entitlement($plan, $cycle, EntitlementStatus::Pending),
            $this->time(),
        );
    }

    private function reconstitutedSubscription(
        SubscriptionStatus $status,
        EntitlementStatus $entitlementStatus,
        int $version,
    ): Subscription {
        $plan = new PlanId($this->uuid(3));
        $cycle = new BillingCycleId($this->uuid(4));

        return Subscription::reconstitute(
            new SubscriptionId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicRegistrationId($this->uuid(5)),
            new PaymentId($this->uuid(6)),
            new CommercialOfferId($this->uuid(7)),
            $plan,
            $cycle,
            new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-01', '2026-07-31'),
            $this->entitlement($plan, $cycle, $entitlementStatus),
            $status,
            $this->time(),
            $this->time('+1 minute'),
            $version,
        );
    }

    /** @param list<string> $capabilities */
    private function entitlement(
        PlanId $plan,
        BillingCycleId $cycle,
        EntitlementStatus $status,
        string $version = 'catalogue-v1',
        array $capabilities = ['configured.capability'],
    ): Entitlement {
        return new Entitlement(
            $plan,
            $cycle,
            $version,
            $status,
            array_map(static fn (string $capability): CapabilityKey => new CapabilityKey($capability), $capabilities),
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-14T00:00:00+00:00');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
