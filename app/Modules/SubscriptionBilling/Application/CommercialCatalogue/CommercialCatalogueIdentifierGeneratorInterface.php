<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

interface CommercialCatalogueIdentifierGeneratorInterface
{
    public function generate(): string;
}
