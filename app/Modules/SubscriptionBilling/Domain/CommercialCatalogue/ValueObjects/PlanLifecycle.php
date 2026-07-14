<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanLifecycleTransitionException;

final readonly class PlanLifecycle
{
    public function __construct(public PlanStatus $status) {}

    public static function draft(): self
    {
        return new self(PlanStatus::Draft);
    }

    public function activate(): self
    {
        return $this->transitionTo(PlanStatus::Active, [PlanStatus::Draft, PlanStatus::Unavailable]);
    }

    public function makeUnavailable(): self
    {
        return $this->transitionTo(PlanStatus::Unavailable, [PlanStatus::Active]);
    }

    public function grandfather(): self
    {
        return $this->transitionTo(PlanStatus::Grandfathered, [PlanStatus::Active, PlanStatus::Unavailable]);
    }

    public function retire(): self
    {
        return $this->transitionTo(PlanStatus::Retired, [
            PlanStatus::Draft,
            PlanStatus::Active,
            PlanStatus::Unavailable,
            PlanStatus::Grandfathered,
        ]);
    }

    public function allowsNewPurchase(): bool
    {
        return $this->status === PlanStatus::Active;
    }

    public function allowsRenewal(): bool
    {
        return in_array($this->status, [PlanStatus::Active, PlanStatus::Grandfathered], true);
    }

    /** @param list<PlanStatus> $allowedFrom */
    private function transitionTo(PlanStatus $target, array $allowedFrom): self
    {
        if (! in_array($this->status, $allowedFrom, true)) {
            throw new InvalidPlanLifecycleTransitionException(sprintf(
                'Plan cannot transition from %s to %s.',
                $this->status->value,
                $target->value,
            ));
        }

        return new self($target);
    }
}
