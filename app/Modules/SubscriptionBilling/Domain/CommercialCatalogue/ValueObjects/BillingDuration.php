<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;

final readonly class BillingDuration
{
    public function __construct(public BillingInterval $interval, public int $intervalCount)
    {
        if ($intervalCount < 1) {
            throw new InvalidCommercialCatalogueValueException('Billing duration interval count must be positive.');
        }
    }
}
