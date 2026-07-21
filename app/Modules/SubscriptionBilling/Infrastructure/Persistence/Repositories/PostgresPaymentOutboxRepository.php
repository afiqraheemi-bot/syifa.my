<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentOutboxRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresPaymentOutboxRepository implements PaymentOutboxRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function add(string $eventId, string $eventType, string $paymentId, array $payload, DateTimeImmutable $occurredAt): void
    {
        $timestamp = $occurredAt->format('Y-m-d H:i:s.uP');
        $this->connection->table('payment_integration_outbox')->insertOrIgnore([
            'id' => $eventId, 'event_type' => $eventType, 'payment_id' => $paymentId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'occurred_at' => $timestamp,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }
}
