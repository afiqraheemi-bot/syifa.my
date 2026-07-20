<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\CommercialCatalogue;

use Illuminate\Database\ConnectionInterface;

final class CommercialCatalogueTransactionalService
{
    private \Closure $execute;

    public function __construct(
        private ConnectionInterface $connection,
        callable $execute,
    ) {
        $this->execute = \Closure::fromCallable($execute);
    }

    public function execute(mixed $command): mixed
    {
        return $this->connection->transaction(
            fn (): mixed => ($this->execute)($command),
        );
    }
}
