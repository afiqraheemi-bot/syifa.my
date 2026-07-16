<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreatePlanService
{
    public function __construct(
        private CommercialCatalogueIdentifierGeneratorInterface $identifiers,
        private PlanRepositoryInterface $plans,
    ) {}

    public function execute(CreatePlanCommand $command): Plan
    {
        $occurredAt = self::instant($command->occurredAt);

        $plan = new Plan(
            new PlanId($this->identifiers->generate()),
            new PlanName($command->name),
            new PlanCode($command->code),
            $command->description,
            PlanLifecycle::draft(),
            $command->displayOrder,
            $occurredAt,
            $occurredAt,
        );

        $this->plans->save($plan);

        return $plan;
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
