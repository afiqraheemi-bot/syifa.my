<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Authorization;

use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationDecision;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;

final readonly class DenyAllCommercialCatalogueAuthorization implements CommercialCatalogueAuthorizationInterface
{
    public function authorize(string $action): CommercialCatalogueAuthorizationDecision
    {
        return new CommercialCatalogueAuthorizationDecision(false);
    }
}
