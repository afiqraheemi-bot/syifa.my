<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRegistrationResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidPaymentStorageStateException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;

final readonly class PostgresProviderWebhookReceiptRepository implements ProviderWebhookReceiptRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function register(NewProviderWebhookReceiptData $data): ProviderWebhookReceiptRegistrationResult
    {
        $now = new DateTimeImmutable;

        // A single atomic statement, run against the injected connection as-is:
        // safe whether called standalone or nested inside a caller's transaction.
        // The (provider_key, provider_event_id) unique index is the final,
        // database-level idempotency guard — not the insertOrIgnore call itself.
        // The id is an opaque surrogate generated fresh on every attempt; on a
        // real duplicate the row already has its own (earlier) id, discarded
        // below in favour of the persisted receipt returned by find().
        $inserted = $this->connection->table('payment_provider_webhook_receipts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'provider_key' => $data->providerKey,
            'provider_event_id' => $data->providerEventId,
            'event_type' => $data->eventType,
            'status' => ProviderWebhookReceiptStatus::Received->value,
            'provider_payment_reference' => $data->providerPaymentReference,
            'payment_attempt_reference' => $data->paymentAttemptReference,
            'payment_id' => $data->paymentId,
            'signature_verified' => $data->signatureVerified,
            'payload_hash' => $data->payloadHash,
            'received_at' => $this->databaseTimestamp($data->receivedAt),
            'created_at' => $this->databaseTimestamp($now),
            'updated_at' => $this->databaseTimestamp($now),
        ]);

        $receipt = $this->find($data->providerKey, $data->providerEventId);

        if ($receipt === null) {
            throw new InvalidPaymentStorageStateException('Provider webhook receipt could not be located immediately after registration.');
        }

        return new ProviderWebhookReceiptRegistrationResult($receipt, wasDuplicate: $inserted === 0);
    }

    public function find(string $providerKey, string $providerEventId): ?ProviderWebhookReceipt
    {
        $row = $this->connection->table('payment_provider_webhook_receipts')
            ->where('provider_key', $providerKey)
            ->where('provider_event_id', $providerEventId)
            ->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    private function map(stdClass $row): ProviderWebhookReceipt
    {
        return new ProviderWebhookReceipt(
            id: $this->stringValue($row, 'id'),
            providerKey: $this->stringValue($row, 'provider_key'),
            providerEventId: $this->stringValue($row, 'provider_event_id'),
            eventType: $this->stringValue($row, 'event_type'),
            status: ProviderWebhookReceiptStatus::from($this->stringValue($row, 'status')),
            receivedAt: $this->dateTimeValue($row->received_at ?? null, 'received_at'),
            providerPaymentReference: $this->nullableStringValue($row->provider_payment_reference ?? null, 'provider_payment_reference'),
            paymentAttemptReference: $this->nullableStringValue($row->payment_attempt_reference ?? null, 'payment_attempt_reference'),
            paymentId: $this->nullableStringValue($row->payment_id ?? null, 'payment_id'),
            signatureVerified: $this->nullableBoolValue($row->signature_verified ?? null),
            payloadHash: $this->nullableStringValue($row->payload_hash ?? null, 'payload_hash'),
            processingStartedAt: $this->nullableDateTimeValue($row->processing_started_at ?? null, 'processing_started_at'),
            processedAt: $this->nullableDateTimeValue($row->processed_at ?? null, 'processed_at'),
            failureLabel: $this->nullableStringValue($row->failure_label ?? null, 'failure_label'),
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidPaymentStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableStringValue(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidPaymentStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function nullableBoolValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidPaymentStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function nullableDateTimeValue(mixed $value, string $field): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTimeValue($value, $field);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
