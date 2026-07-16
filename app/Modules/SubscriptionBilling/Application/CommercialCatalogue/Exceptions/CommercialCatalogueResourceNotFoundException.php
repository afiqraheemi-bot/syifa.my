<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions;

use RuntimeException;

final class CommercialCatalogueResourceNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $resource,
        public readonly string $identifier,
    ) {
        parent::__construct(sprintf('%s resource not found for identifier %s.', $resource, $identifier));
    }
}
