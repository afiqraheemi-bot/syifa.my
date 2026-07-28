<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

enum ExpiryAuthority: string
{
    case Provider = 'provider';
    case CommercialOffer = 'commercial_offer';
    case None = 'none';
}
