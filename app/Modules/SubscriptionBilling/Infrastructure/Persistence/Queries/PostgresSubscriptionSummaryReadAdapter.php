<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresSubscriptionSummaryReadAdapter implements SubscriptionSummaryReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $trustedTenantId): ?SubscriptionSummaryData
    {
        $row = $this->connection->table('subscriptions')
            ->where('tenant_id', $trustedTenantId)
            ->first(['status', 'ends_on']);

        return $row === null
            ? null
            : new SubscriptionSummaryData((string) $row->status, substr((string) $row->ends_on, 0, 10));
    }
}
