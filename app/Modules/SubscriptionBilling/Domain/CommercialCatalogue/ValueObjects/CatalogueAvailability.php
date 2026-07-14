<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

enum CatalogueAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
