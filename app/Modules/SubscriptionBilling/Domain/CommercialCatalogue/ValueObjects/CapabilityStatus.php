<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

enum CapabilityStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
