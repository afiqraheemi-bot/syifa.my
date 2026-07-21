<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\TransientPaymentApplicationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationTransactionInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StalePaymentWriteException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

final readonly class PostgresPaymentApplicationTransaction implements PaymentApplicationTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        try {
            return $this->connection->transaction(static fn (): mixed => $operation());
        } catch (StalePaymentWriteException|QueryException $exception) {
            throw new TransientPaymentApplicationException('Payment application transaction failed transiently.', 0, $exception);
        }
    }
}
