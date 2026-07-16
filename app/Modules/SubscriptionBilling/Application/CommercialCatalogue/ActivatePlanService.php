<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use DateTimeImmutable;
use DateTimeZone;

/**
 * `occurredAt` is command/audit metadata carried on every privileged mutation.
 * Plan additionally consumes it as Domain lifecycle time (see Plan::activate()).
 */
final readonly class ActivatePlanService
{
    public function __construct(private PlanRepositoryInterface $plans) {}

    public function execute(ActivatePlanCommand $command): Plan
    {
        $plan = $this->requirePlan($command->planId);
        $this->assertExpectedVersion($plan, $command->expectedVersion);

        $updated = $plan->activate(self::instant($command->occurredAt));
        $this->plans->save($updated);

        return $updated;
    }

    private function requirePlan(string $planId): Plan
    {
        $plan = $this->plans->findById(new PlanId($planId));

        if ($plan === null) {
            throw new CommercialCatalogueResourceNotFoundException('Plan', $planId);
        }

        return $plan;
    }

    private function assertExpectedVersion(Plan $plan, int $expectedVersion): void
    {
        if ($plan->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Plan',
                $plan->id->value,
                $expectedVersion,
                $plan->version(),
            );
        }
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
