<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UpdatePlanDetailsService
{
    public function execute(Plan $plan, UpdatePlanDetailsCommand $command): Plan
    {
        if ($plan->id->value !== $command->planId) {
            throw new InvalidCommercialCatalogueValueException(
                'The requested Plan does not match the provided Plan details.',
            );
        }

        return new Plan(
            $plan->id,
            new PlanName($command->name),
            $plan->code,
            $command->description,
            $plan->lifecycle,
            $command->displayOrder,
            $plan->createdAt,
            self::instant($command->occurredAt),
        );
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
