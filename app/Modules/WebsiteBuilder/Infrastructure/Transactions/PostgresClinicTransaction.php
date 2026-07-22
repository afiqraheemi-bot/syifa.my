<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Transactions;

use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicTransaction implements ClinicTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $operation): mixed
    {
        return $this->connection->transaction(static fn (): mixed => $operation());
    }
}
