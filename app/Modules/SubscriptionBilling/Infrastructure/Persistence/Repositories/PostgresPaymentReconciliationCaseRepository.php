<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentReconciliationCaseRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class PostgresPaymentReconciliationCaseRepository implements PaymentReconciliationCaseRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function open(string $receiptId, string $paymentId, string $attemptReference, string $reasonCode, DateTimeImmutable $openedAt): string
    {
        $id = (string) Str::uuid();
        $timestamp = $openedAt->format('Y-m-d H:i:s.uP');
        $this->connection->table('payment_reconciliation_cases')->insertOrIgnore([
            'id' => $id, 'provider_webhook_receipt_id' => $receiptId, 'payment_id' => $paymentId,
            'payment_attempt_reference' => $attemptReference, 'status' => 'open', 'reason_code' => $reasonCode,
            'opened_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);

        return (string) $this->connection->table('payment_reconciliation_cases')->where('provider_webhook_receipt_id', $receiptId)->value('id');
    }
}
