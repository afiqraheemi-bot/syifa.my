<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions;

use RuntimeException;

final class CommercialCatalogueVersionMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $resource,
        public readonly string $identifier,
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(sprintf(
            '%s resource version mismatch for identifier %s.',
            $resource,
            $identifier,
        ));
    }
}
