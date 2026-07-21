<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\Commercial\Contracts\Data\AvailableCommercialOfferData;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingQueryInterface;
use DateTimeImmutable;

final readonly class ListAvailableCommercialOffersService
{
    public function __construct(private PlanOfferingQueryInterface $planOfferings) {}

    /**
     * @return list<AvailableCommercialOfferData>
     */
    public function execute(DateTimeImmutable $occurredAt): array
    {
        return array_map(
            static fn ($offering): AvailableCommercialOfferData => new AvailableCommercialOfferData(
                $offering->planOfferingId,
                $offering->planId,
                $offering->billingCycleId,
                $offering->planName,
                $offering->billingCycleName,
                $offering->amountMinor,
                $offering->currency,
                $offering->billingPeriodStart,
                null,
                $offering->configurationVersion,
                $offering->displayOrder,
            ),
            $this->planOfferings->listAvailable($occurredAt->format('Y-m-d')),
        );
    }
}
