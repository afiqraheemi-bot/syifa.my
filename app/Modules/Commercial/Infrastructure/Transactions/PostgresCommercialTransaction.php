<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Transactions;

use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresCommercialTransaction implements CommercialTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $operation());
    }
}
