<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingSelectionData;

final readonly class ResolveSubscriptionOfferingService
{
    public function __construct(private SubscriptionOfferingResolverInterface $resolver) {}

    public function execute(SubscriptionOfferingSelectionData $selection): ?ResolvedSubscriptionOfferingData
    {
        return $this->resolver->resolve($selection);
    }
}
