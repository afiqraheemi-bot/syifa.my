<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Unavailable = 'unavailable';
    case Grandfathered = 'grandfathered';
    case Retired = 'retired';
}
