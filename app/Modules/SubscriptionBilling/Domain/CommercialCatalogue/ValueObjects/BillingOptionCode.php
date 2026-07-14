<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;

final readonly class BillingOptionCode
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $value) !== 1) {
            throw new InvalidCommercialCatalogueValueException(
                'Billing Option code has an invalid stable identifier.',
            );
        }
    }
}
