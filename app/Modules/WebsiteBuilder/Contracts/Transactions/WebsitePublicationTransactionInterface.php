<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Transactions;

interface WebsitePublicationTransactionInterface
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(
        string $tenantId,
        string $websiteId,
        callable $operation,
    ): mixed;
}
