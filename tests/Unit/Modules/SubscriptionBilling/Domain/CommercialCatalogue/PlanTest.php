<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlanTest extends TestCase
{
    public function test_plan_carries_every_required_governed_reference_field(): void
    {
        $plan = $this->plan();

        self::assertSame($this->uuid(1), $plan->id->value);
        self::assertSame('syifa_managed_website', $plan->code->value);
        self::assertSame('Syifa Managed Website', $plan->name->value);
        self::assertSame('Managed website subscription for an eligible clinic.', $plan->description);
        self::assertSame(PlanStatus::Draft, $plan->lifecycle->status);
        self::assertSame(0, $plan->displayOrder);
        self::assertEquals($this->time(), $plan->createdAt);
        self::assertEquals($this->time(), $plan->lastChangedAt);
        self::assertFalse($plan->isAvailableForNewPurchase());
    }

    public function test_display_order_may_be_zero_or_positive(): void
    {
        self::assertSame(0, $this->plan(displayOrder: 0)->displayOrder);
        self::assertSame(25, $this->plan(displayOrder: 25)->displayOrder);
    }

    public function test_negative_display_order_is_rejected(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->plan(displayOrder: -1);
    }

    public function test_last_changed_time_cannot_precede_creation(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->plan(createdAt: $this->time(), lastChangedAt: $this->time('-1 second'));
    }

    public function test_named_activation_and_unavailability_transitions_update_change_time(): void
    {
        $draft = $this->plan();
        $active = $draft->activate($this->time('+1 minute'));
        $unavailable = $active->makeUnavailable($this->time('+2 minutes'));
        $reactivated = $unavailable->activate($this->time('+3 minutes'));

        self::assertSame(PlanStatus::Draft, $draft->lifecycle->status);
        self::assertSame(PlanStatus::Active, $active->lifecycle->status);
        self::assertSame(PlanStatus::Unavailable, $unavailable->lifecycle->status);
        self::assertSame(PlanStatus::Active, $reactivated->lifecycle->status);
        self::assertEquals($this->time('+3 minutes'), $reactivated->lastChangedAt);
        self::assertEquals($this->time(), $reactivated->createdAt);
    }

    public function test_active_or_unavailable_plan_can_be_grandfathered(): void
    {
        $active = $this->plan()->activate($this->time('+1 minute'));
        $fromActive = $active->grandfather($this->time('+2 minutes'));
        $fromUnavailable = $active
            ->makeUnavailable($this->time('+2 minutes'))
            ->grandfather($this->time('+3 minutes'));

        self::assertSame(PlanStatus::Grandfathered, $fromActive->lifecycle->status);
        self::assertSame(PlanStatus::Grandfathered, $fromUnavailable->lifecycle->status);
        self::assertFalse($fromActive->isAvailableForNewPurchase());
        self::assertTrue($fromActive->isAvailableForRenewal());
    }

    public function test_draft_active_unavailable_and_grandfathered_plan_can_be_retired(): void
    {
        $draft = $this->plan();
        $active = $draft->activate($this->time('+1 minute'));
        $unavailable = $active->makeUnavailable($this->time('+2 minutes'));
        $grandfathered = $unavailable->grandfather($this->time('+3 minutes'));

        self::assertSame(PlanStatus::Retired, $draft->retire($this->time('+1 minute'))->lifecycle->status);
        self::assertSame(PlanStatus::Retired, $active->retire($this->time('+2 minutes'))->lifecycle->status);
        self::assertSame(PlanStatus::Retired, $unavailable->retire($this->time('+3 minutes'))->lifecycle->status);
        self::assertSame(PlanStatus::Retired, $grandfathered->retire($this->time('+4 minutes'))->lifecycle->status);
    }

    public function test_retired_plan_is_terminal(): void
    {
        $retired = $this->plan()->retire($this->time('+1 minute'));

        self::assertFalse($retired->isAvailableForNewPurchase());
        self::assertFalse($retired->isAvailableForRenewal());
        $this->expectException(InvalidPlanLifecycleTransitionException::class);
        $retired->activate($this->time('+2 minutes'));
    }

    public function test_backdated_lifecycle_change_fails_closed(): void
    {
        $active = $this->plan()->activate($this->time('+2 minutes'));

        $this->expectException(InvalidCommercialCatalogueValueException::class);
        $active->makeUnavailable($this->time('+1 minute'));
    }

    private function plan(
        int $displayOrder = 0,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $lastChangedAt = null,
    ): Plan {
        return new Plan(
            new PlanId($this->uuid(1)),
            new PlanName('Syifa Managed Website'),
            new PlanCode('syifa_managed_website'),
            'Managed website subscription for an eligible clinic.',
            PlanLifecycle::draft(),
            $displayOrder,
            $createdAt ?? $this->time(),
            $lastChangedAt ?? $createdAt ?? $this->time(),
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-14T08:00:00+00:00');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
