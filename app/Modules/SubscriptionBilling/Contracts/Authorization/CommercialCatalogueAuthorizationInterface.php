<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Authorization;

interface CommercialCatalogueAuthorizationInterface
{
    public function authorize(string $action): CommercialCatalogueAuthorizationDecision;
}
