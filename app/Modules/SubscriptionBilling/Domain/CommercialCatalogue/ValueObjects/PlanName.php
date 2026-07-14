<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;

final readonly class PlanName
{
    public function __construct(public string $value)
    {
        if ($value === '' || trim($value) !== $value || mb_strlen($value) > 100) {
            throw new InvalidCommercialCatalogueValueException('Plan name must be a normalized value of at most 100 characters.');
        }
    }
}
