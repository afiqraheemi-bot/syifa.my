<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Transactions;

use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresBookingTransaction implements BookingTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $operation());
    }
}
