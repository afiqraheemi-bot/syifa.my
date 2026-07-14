<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanOfferingException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanOfferingTest extends TestCase
{
    public function test_offering_is_created_as_draft_with_all_required_fields(): void
    {
        $offering = $this->offering();

        self::assertSame(PlanOfferingStatus::Draft, $offering->status);
        self::assertSame($this->uuid(4), $offering->id->value);
        self::assertSame($this->uuid(1), $offering->planId->value);
        self::assertSame($this->uuid(2), $offering->billingOptionId->value);
        self::assertSame(12500, $offering->currentPrice->amountMinor);
        self::assertIsInt($offering->currentPrice->amountMinor);
        self::assertSame('MYR', $offering->currentPrice->currencyCode);
        self::assertSame('catalogue-v1', $offering->configurationVersion);
        self::assertSame('capability-package-v1', $offering->capabilityConfigurationReference);
        self::assertSame(0, $offering->displayOrder);
    }

    public function test_draft_is_not_purchasable_or_renewable(): void
    {
        $offering = $this->offering();

        self::assertFalse($offering->isAvailableForNewPurchase($this->plan(), $this->billingOption(), '2026-07-01'));
        self::assertFalse($offering->isAvailableForRenewal($this->plan(), $this->billingOption(), '2026-07-01'));
    }

    public function test_draft_activates_and_active_offering_supports_eligible_purchase_and_renewal(): void
    {
        $active = $this->offering()->activate();

        self::assertSame(PlanOfferingStatus::Active, $active->status);
        self::assertTrue($active->isAvailableForNewPurchase($this->plan(), $this->billingOption(), '2026-07-01'));
        self::assertTrue($active->isAvailableForRenewal($this->plan(), $this->billingOption(), '2026-07-01'));
    }

    public function test_active_offering_can_be_made_unavailable(): void
    {
        $unavailable = $this->offering()->activate()->makeUnavailable();

        self::assertSame(PlanOfferingStatus::Unavailable, $unavailable->status);
        self::assertFalse($unavailable->isAvailableForNewPurchase($this->plan(), $this->billingOption(), '2026-07-01'));
        self::assertFalse($unavailable->isAvailableForRenewal($this->plan(), $this->billingOption(), '2026-07-01'));
    }

    public function test_active_or_unavailable_offering_can_be_grandfathered(): void
    {
        $active = $this->offering()->activate();
        $fromActive = $active->grandfather();
        $fromUnavailable = $active->makeUnavailable()->grandfather();

        self::assertSame(PlanOfferingStatus::Grandfathered, $fromActive->status);
        self::assertSame(PlanOfferingStatus::Grandfathered, $fromUnavailable->status);
    }

    public function test_grandfathered_offering_blocks_new_purchase_but_may_remain_renewal_eligible(): void
    {
        $offering = $this->offering()->activate()->grandfather();
        $plan = $this->plan(PlanStatus::Grandfathered);

        self::assertFalse($offering->isAvailableForNewPurchase($plan, $this->billingOption(), '2026-07-01'));
        self::assertTrue($offering->isAvailableForRenewal($plan, $this->billingOption(), '2026-07-01'));
    }

    #[DataProvider('retirableStatusProvider')]
    public function test_every_approved_state_can_be_retired(PlanOfferingStatus $status): void
    {
        $offering = match ($status) {
            PlanOfferingStatus::Draft => $this->offering(),
            PlanOfferingStatus::Active => $this->offering()->activate(),
            PlanOfferingStatus::Unavailable => $this->offering()->activate()->makeUnavailable(),
            PlanOfferingStatus::Grandfathered => $this->offering()->activate()->grandfather(),
            PlanOfferingStatus::Retired => self::fail('Retired is not a source transition.'),
        };

        self::assertSame(PlanOfferingStatus::Retired, $offering->retire()->status);
    }

    /** @return iterable<string, array{PlanOfferingStatus}> */
    public static function retirableStatusProvider(): iterable
    {
        yield 'draft' => [PlanOfferingStatus::Draft];
        yield 'active' => [PlanOfferingStatus::Active];
        yield 'unavailable' => [PlanOfferingStatus::Unavailable];
        yield 'grandfathered' => [PlanOfferingStatus::Grandfathered];
    }

    public function test_retired_offering_is_terminal(): void
    {
        $retired = $this->offering()->retire();

        $this->expectException(InvalidPlanOfferingException::class);
        $retired->activate();
    }

    public function test_non_myr_price_is_rejected(): void
    {
        $this->expectException(InvalidPlanOfferingException::class);

        $this->offering(price: new Money(12500, 'USD'));
    }

    public function test_negative_display_order_is_rejected(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->offering(displayOrder: -1);
    }

    public function test_invalid_effective_period_is_rejected(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->offering(effectivePeriod: new EffectivePeriod('2026-08-01', '2026-07-31'));
    }

    public function test_offering_is_unavailable_outside_its_effective_period(): void
    {
        $active = $this->offering()->activate();

        self::assertFalse($active->isAvailableForNewPurchase($this->plan(), $this->billingOption(), '2026-06-30'));
    }

    public function test_non_recurring_lifetime_offering_remains_unavailable_in_phase_one(): void
    {
        $active = $this->offering()->activate();
        $nonRecurring = $this->billingOption(
            RecurrenceClassification::NonRecurring,
            null,
            CatalogueAvailability::Unavailable,
        );

        self::assertFalse($active->isAvailableForNewPurchase($this->plan(), $nonRecurring, '2026-07-01'));
        self::assertFalse($active->isAvailableForRenewal($this->plan(), $nonRecurring, '2026-07-01'));
    }

    public function test_cross_record_substitution_is_rejected(): void
    {
        $otherPlan = new Plan(
            new PlanId($this->uuid(99)),
            new PlanName('Other configured Plan'),
            new PlanCode('other_configured_plan'),
            'A distinct governed Plan.',
            new PlanLifecycle(PlanStatus::Active),
            0,
            $this->time(),
            $this->time(),
        );

        $this->expectException(InvalidPlanOfferingException::class);
        $this->offering()->activate()->isAvailableForNewPurchase($otherPlan, $this->billingOption(), '2026-07-01');
    }

    private function plan(PlanStatus $status = PlanStatus::Active): Plan
    {
        return new Plan(
            new PlanId($this->uuid(1)),
            new PlanName('Configured Plan'),
            new PlanCode('configured_plan'),
            'A governed commercial Plan.',
            new PlanLifecycle($status),
            0,
            $this->time(),
            $this->time(),
        );
    }

    private function billingOption(
        RecurrenceClassification $recurrence = RecurrenceClassification::Recurring,
        ?BillingDuration $duration = null,
        CatalogueAvailability $availability = CatalogueAvailability::Available,
    ): BillingOption {
        return new BillingOption(
            new BillingOptionId($this->uuid(2)),
            new BillingOptionCode('configured_option'),
            new BillingOptionName('Configured billing option'),
            $availability,
            $recurrence,
            $duration ?? ($recurrence === RecurrenceClassification::Recurring
                ? new BillingDuration(BillingInterval::Month, 1)
                : null),
            new EffectivePeriod('2026-07-01'),
            0,
        );
    }

    private function offering(
        ?Money $price = null,
        int $displayOrder = 0,
        ?EffectivePeriod $effectivePeriod = null,
    ): PlanOffering {
        return new PlanOffering(
            new PlanOfferingId($this->uuid(4)),
            new PlanId($this->uuid(1)),
            new BillingOptionId($this->uuid(2)),
            $price ?? new Money(12500, 'MYR'),
            $effectivePeriod ?? new EffectivePeriod('2026-07-01'),
            PlanOfferingStatus::Draft,
            'catalogue-v1',
            'capability-package-v1',
            $displayOrder,
        );
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-14T08:00:00+00:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
