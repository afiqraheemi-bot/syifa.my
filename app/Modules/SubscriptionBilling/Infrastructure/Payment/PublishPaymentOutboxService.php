<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;
use Throwable;

final readonly class PublishPaymentOutboxService
{
    private const string RETRY_FAILURE_LABEL = 'outbox_publish_failed';

    public function __construct(private ConnectionInterface $connection, private Dispatcher $events) {}

    public function publishNext(): bool
    {
        $now = new DateTimeImmutable;
        $token = (string) Str::uuid();
        $timestamp = $now->format('Y-m-d H:i:s.uP');
        $lease = $now->modify('+2 minutes')->format('Y-m-d H:i:s.uP');
        $rows = $this->connection->select(<<<'SQL'
            UPDATE payment_integration_outbox SET publish_claim_token=?, publish_lease_expires_at=?,
                publish_attempt_count=publish_attempt_count+1, updated_at=?
            WHERE id=(SELECT id FROM payment_integration_outbox WHERE published_at IS NULL
                AND (next_publish_attempt_at IS NULL OR next_publish_attempt_at<=?)
                AND (publish_claim_token IS NULL OR publish_lease_expires_at<=?) ORDER BY occurred_at FOR UPDATE SKIP LOCKED LIMIT 1)
            RETURNING *
            SQL, [$token, $lease, $timestamp, $timestamp, $timestamp]);
        $row = $rows[0] ?? null;
        if (! $row instanceof stdClass) {
            return false;
        }

        try {
            $payload = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException('Payment outbox payload is malformed.');
            }
            $this->events->dispatch(new PaymentIntegrationOutboxEvent((string) $row->id, (string) $row->event_type, (int) $row->event_version, (string) $row->payment_id, $payload, new DateTimeImmutable((string) $row->occurred_at)));
        } catch (Throwable) {
            // Delivery failed; release the lease on a safe retry schedule
            // instead of leaving the row claimed until the lease naturally
            // expires. Never persist the caught exception's own message.
            $retryAt = $now->modify('+30 seconds')->format('Y-m-d H:i:s.uP');
            $this->connection->table('payment_integration_outbox')
                ->where('id', $row->id)->where('publish_claim_token', $token)->whereNull('published_at')
                ->update([
                    'publish_claim_token' => null, 'publish_lease_expires_at' => null,
                    'next_publish_attempt_at' => $retryAt, 'safe_failure_label' => self::RETRY_FAILURE_LABEL,
                    'updated_at' => $timestamp,
                ]);

            return true;
        }

        $this->connection->table('payment_integration_outbox')->where('id', $row->id)->where('publish_claim_token', $token)->whereNull('published_at')->update([
            'published_at' => $timestamp, 'publish_claim_token' => null, 'publish_lease_expires_at' => null,
            'next_publish_attempt_at' => null, 'safe_failure_label' => null, 'updated_at' => $timestamp,
        ]);

        return true;
    }
}
