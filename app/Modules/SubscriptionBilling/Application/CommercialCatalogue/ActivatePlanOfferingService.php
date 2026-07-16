<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;

/**
 * `occurredAt` is command/audit metadata carried on every privileged mutation.
 * Plan Offering retains it for future durable audit delivery but does not
 * persist it as Domain state — no audit delivery, events, queues, or outbox
 * behavior is implemented here.
 */
final readonly class ActivatePlanOfferingService
{
    public function __construct(private PlanOfferingRepositoryInterface $planOfferings) {}

    public function execute(ActivatePlanOfferingCommand $command): PlanOffering
    {
        $planOffering = $this->requirePlanOffering($command->planOfferingId);
        $this->assertExpectedVersion($planOffering, $command->expectedVersion);

        $updated = $planOffering->activate();
        $this->planOfferings->save($updated);

        return $updated;
    }

    private function requirePlanOffering(string $planOfferingId): PlanOffering
    {
        $planOffering = $this->planOfferings->findById(new PlanOfferingId($planOfferingId));

        if ($planOffering === null) {
            throw new CommercialCatalogueResourceNotFoundException('Plan Offering', $planOfferingId);
        }

        return $planOffering;
    }

    private function assertExpectedVersion(PlanOffering $planOffering, int $expectedVersion): void
    {
        if ($planOffering->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Plan Offering',
                $planOffering->id->value,
                $expectedVersion,
                $planOffering->version(),
            );
        }
    }
}
