<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Transactions;

use App\Modules\WebsiteBuilder\Contracts\Transactions\WebsitePublicationTransactionInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsitePublicationTransaction implements WebsitePublicationTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(string $tenantId, string $websiteId, callable $operation): mixed
    {
        return $this->connection->transaction(function () use ($tenantId, $websiteId, $operation): mixed {
            $this->connection->table('websites')
                ->where('tenant_id', $tenantId)
                ->where('id', $websiteId)
                ->lockForUpdate()
                ->first(['id']);
            $this->connection->table('website_drafts')
                ->where('tenant_id', $tenantId)
                ->where('website_id', $websiteId)
                ->lockForUpdate()
                ->first(['website_id']);

            return $operation();
        });
    }
}
