<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptClaim;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptCompletion;
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

    public function findById(string $receiptId): ?ProviderWebhookReceipt
    {
        $row = $this->connection->table('payment_provider_webhook_receipts')->where('id', $receiptId)->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function claim(string $receiptId, DateTimeImmutable $now, int $leaseSeconds): ?ProviderWebhookReceiptClaim
    {
        $claimToken = (string) Str::uuid();
        $timestamp = $this->databaseTimestamp($now);
        $leaseExpiresAt = $this->databaseTimestamp($now->modify(sprintf('+%d seconds', $leaseSeconds)));
        $rows = $this->connection->select(<<<'SQL'
            UPDATE payment_provider_webhook_receipts
            SET status = 'processing', processing_claim_token = ?, processing_started_at = ?,
                processing_lease_expires_at = ?, verification_attempt_count = verification_attempt_count + 1,
                last_verification_attempt_at = ?, next_verification_attempt_at = NULL, updated_at = ?
            WHERE id = ? AND (
                status = 'received'
                OR (status = 'retry_pending' AND next_verification_attempt_at <= ?)
                OR (status = 'processing' AND processing_lease_expires_at <= ?)
            )
            RETURNING *
            SQL, [$claimToken, $timestamp, $leaseExpiresAt, $timestamp, $timestamp, $receiptId, $timestamp, $timestamp]);

        $row = $rows[0] ?? null;

        return $row instanceof stdClass ? new ProviderWebhookReceiptClaim($this->map($row), $claimToken) : null;
    }

    public function complete(string $receiptId, string $claimToken, ProviderWebhookReceiptCompletion $completion): bool
    {
        $attempt = $completion->attempt;
        $verification = $completion->verification;
        $affected = $this->connection->table('payment_provider_webhook_receipts')
            ->where('id', $receiptId)
            ->where('processing_claim_token', $claimToken)
            ->where('status', ProviderWebhookReceiptStatus::Processing->value)
            ->update([
                'status' => $completion->status->value,
                'processing_claim_token' => null,
                'processing_lease_expires_at' => null,
                'processed_at' => $completion->status === ProviderWebhookReceiptStatus::RetryPending ? null : $this->databaseTimestamp($completion->occurredAt),
                'next_verification_attempt_at' => $completion->nextVerificationAttemptAt === null ? null : $this->databaseTimestamp($completion->nextVerificationAttemptAt),
                'safe_failure_label' => $completion->safeFailureLabel,
                'resolved_payment_id' => $attempt?->paymentId,
                'resolved_payment_attempt_reference' => $attempt?->attemptReference,
                'resolved_attempt_relation' => $attempt === null ? null : ($attempt->isCurrent ? 'current' : 'historical'),
                'verification_outcome' => $verification?->outcome->value,
                'verified_amount_minor' => $verification?->verifiedAmountMinor,
                'verified_currency' => $verification?->verifiedCurrency,
                'provider_object_correlation_passed' => $verification?->providerObjectCorrelationPassed,
                'environment_correlation_supported' => $verification?->environmentCorrelationSupported,
                'environment_correlation_passed' => $verification?->environmentCorrelationPassed,
                'authoritative_verified_at' => $verification === null ? null : $this->databaseTimestamp($verification->verifiedAt),
                'updated_at' => $this->databaseTimestamp($completion->occurredAt),
            ]);

        return $affected === 1;
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
            failureLabel: $this->nullableStringValue($row->safe_failure_label ?? $row->failure_label ?? null, 'safe_failure_label'),
            processingClaimToken: $this->nullableStringValue($row->processing_claim_token ?? null, 'processing_claim_token'),
            processingLeaseExpiresAt: $this->nullableDateTimeValue($row->processing_lease_expires_at ?? null, 'processing_lease_expires_at'),
            verificationAttemptCount: (int) ($row->verification_attempt_count ?? 0),
            lastVerificationAttemptAt: $this->nullableDateTimeValue($row->last_verification_attempt_at ?? null, 'last_verification_attempt_at'),
            nextVerificationAttemptAt: $this->nullableDateTimeValue($row->next_verification_attempt_at ?? null, 'next_verification_attempt_at'),
            resolvedPaymentId: $this->nullableStringValue($row->resolved_payment_id ?? null, 'resolved_payment_id'),
            resolvedPaymentAttemptReference: $this->nullableStringValue($row->resolved_payment_attempt_reference ?? null, 'resolved_payment_attempt_reference'),
            resolvedAttemptRelation: $this->nullableStringValue($row->resolved_attempt_relation ?? null, 'resolved_attempt_relation'),
            verificationOutcome: $this->nullableStringValue($row->verification_outcome ?? null, 'verification_outcome'),
            verifiedAmountMinor: $row->verified_amount_minor === null ? null : (int) $row->verified_amount_minor,
            verifiedCurrency: $this->nullableStringValue($row->verified_currency ?? null, 'verified_currency'),
            providerObjectCorrelationPassed: $this->nullableBoolValue($row->provider_object_correlation_passed ?? null),
            environmentCorrelationSupported: $this->nullableBoolValue($row->environment_correlation_supported ?? null),
            environmentCorrelationPassed: $this->nullableBoolValue($row->environment_correlation_passed ?? null),
            authoritativeVerifiedAt: $this->nullableDateTimeValue($row->authoritative_verified_at ?? null, 'authoritative_verified_at'),
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
