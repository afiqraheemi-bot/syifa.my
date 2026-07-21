<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Payment\WebhookReceiptRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebhookReceiptRepository implements WebhookReceiptRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function hasProcessed(string $providerKey, string $providerEventId): bool
    {
        return $this->connection->table('payment_provider_webhook_receipts')
            ->where('provider_key', $providerKey)
            ->where('provider_event_id', $providerEventId)
            ->exists();
    }

    public function recordProcessed(string $providerKey, string $providerEventId, string $processingStatus, DateTimeImmutable $occurredAt, string $correlationId): void
    {
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('payment_provider_webhook_receipts')->insert([
            'id' => self::deterministicReceiptId($providerKey, $providerEventId),
            'provider_key' => $providerKey,
            'provider_event_id' => $providerEventId,
            'processing_status' => $processingStatus,
            'occurred_at' => $this->databaseTimestamp($occurredAt),
            'correlation_id' => $correlationId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private static function deterministicReceiptId(string $providerKey, string $providerEventId): string
    {
        $hex = substr(hash('sha256', $providerKey.'|'.$providerEventId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
