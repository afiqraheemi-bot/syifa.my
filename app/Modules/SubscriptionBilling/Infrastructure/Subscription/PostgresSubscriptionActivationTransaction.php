<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Subscription\Exceptions\TransientSubscriptionActivationException;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationTransactionInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

final readonly class PostgresSubscriptionActivationTransaction implements SubscriptionActivationTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        try {
            return $this->connection->transaction(static fn (): mixed => $operation());
        } catch (QueryException $exception) {
            throw new TransientSubscriptionActivationException('Subscription activation transaction failed.', 0, $exception);
        }
    }
}
