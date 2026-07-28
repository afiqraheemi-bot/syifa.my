<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationDecisionTransactionInterface;
use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class ClinicRegistrationDatabaseTransaction implements ClinicRegistrationDecisionTransactionInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(Closure $operation): mixed
    {
        return $this->connection->transaction($operation);
    }
}
