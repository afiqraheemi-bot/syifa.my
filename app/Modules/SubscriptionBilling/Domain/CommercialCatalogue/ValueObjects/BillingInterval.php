<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';
}
