<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\RetirePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;

/**
 * `occurredAt` is command/audit metadata carried on every privileged mutation.
 * Plan Offering retains it for future durable audit delivery but does not
 * persist it as Domain state — no audit delivery, events, queues, or outbox
 * behavior is implemented here.
 */
final readonly class RetirePlanOfferingService
{
    private const string AUDIT_ACTION = 'commercial_catalogue.plan_offering.retire';

    public function __construct(
        private PlanOfferingRepositoryInterface $planOfferings,
        private PlanOfferingAuditTrail $audit,
    ) {}

    public function execute(RetirePlanOfferingCommand $command): PlanOffering
    {
        $planOffering = $this->requirePlanOffering($command->planOfferingId);
        $this->assertExpectedVersion($planOffering, $command->expectedVersion);

        $updated = $planOffering->retire();
        $this->planOfferings->save($updated);
        $this->audit->record(
            self::AUDIT_ACTION,
            $updated,
            $planOffering->version(),
            $planOffering->status->value,
            $command->occurredAt,
            $command->actorPlatformIdentityId,
            $command->correlationId,
        );

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
