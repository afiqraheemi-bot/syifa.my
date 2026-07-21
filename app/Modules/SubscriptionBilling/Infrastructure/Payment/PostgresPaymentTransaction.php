<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresPaymentTransaction implements PaymentTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $operation());
    }
}
