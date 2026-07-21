<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationReconciliationCaseRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class PostgresSubscriptionActivationReconciliationCaseRepository implements SubscriptionActivationReconciliationCaseRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function open(string $applicationId, string $paymentId, string $tenantId, string $reasonCode, DateTimeImmutable $openedAt): string
    {
        $id = (string) Str::uuid();
        $timestamp = $openedAt->format('Y-m-d H:i:s.uP');
        $this->connection->table('subscription_activation_reconciliation_cases')->insertOrIgnore([
            'id' => $id, 'subscription_activation_application_id' => $applicationId, 'payment_id' => $paymentId,
            'tenant_id' => $tenantId, 'reason_code' => $reasonCode, 'status' => 'open', 'opened_at' => $timestamp,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $row = $this->connection->table('subscription_activation_reconciliation_cases')->where('subscription_activation_application_id', $applicationId)->first();

        if ($row === null) {
            throw new RuntimeException('Subscription activation reconciliation case could not be loaded.');
        }

        return (string) $row->id;
    }
}
