<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidPaymentStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StalePaymentWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PaymentAttemptStorageRecord;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\PaymentStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private PaymentPersistenceMapper $mapper,
    ) {}

    public function find(PaymentId $paymentId): ?Payment
    {
        $row = $this->connection->table('payments')->where('id', $paymentId->value)->first();

        return $row === null ? null : $this->paymentFromRow($row);
    }

    public function findByIdempotencyKey(IdempotencyKey $idempotencyKey): ?Payment
    {
        $row = $this->connection->table('payments')->where('idempotency_key', $idempotencyKey->value)->first();

        return $row === null ? null : $this->paymentFromRow($row);
    }

    public function findByProviderReference(ProviderReference $providerReference): ?Payment
    {
        $row = $this->connection->table('payments')
            ->where('provider_key', $providerReference->providerKey)
            ->where('provider_payment_reference', $providerReference->providerPaymentReference)
            ->first();

        return $row === null ? null : $this->paymentFromRow($row);
    }

    public function save(Payment $payment): void
    {
        $newVersion = $this->connection->transaction(
            fn (): int => $payment->version() === 0 ? $this->insert($payment) : $this->update($payment),
        );

        $payment->synchronizeVersion($newVersion);
    }

    private function insert(Payment $payment): int
    {
        $record = $this->mapper->paymentRecord($payment);
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('payments')->insert([
            ...$this->payload($record, 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->replaceAttempts($payment);

        return 1;
    }

    private function update(Payment $payment): int
    {
        $record = $this->mapper->paymentRecord($payment);
        $newVersion = $record->version + 1;
        $affected = $this->connection->table('payments')
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update([
                ...$this->payload($record, $newVersion),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);

        if ($affected !== 1) {
            throw new StalePaymentWriteException('Payment write rejected because its version is stale.');
        }

        $this->replaceAttempts($payment);

        return $newVersion;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PaymentStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id,
            'commercial_offer_id' => $record->commercialOfferId,
            'clinic_registration_id' => $record->clinicRegistrationId,
            'platform_identity_id' => $record->platformIdentityId,
            'tenant_id' => $record->tenantId,
            'amount_minor' => $record->amountMinor,
            'currency' => $record->currency,
            'idempotency_key' => $record->idempotencyKey,
            'status' => $record->status,
            'provider_key' => $record->providerKey,
            'provider_payment_reference' => $record->providerPaymentReference,
            'failure_reason_code' => $record->failureReasonCode,
            'domain_created_at' => $this->databaseTimestamp($record->createdAt),
            'domain_last_changed_at' => $this->databaseTimestamp($record->lastChangedAt),
            'version' => $version,
        ];
    }

    private function replaceAttempts(Payment $payment): void
    {
        $this->connection->table('payment_attempts')->where('payment_id', $payment->id->value)->delete();

        foreach ($this->mapper->attemptRecords($payment) as $record) {
            $this->connection->table('payment_attempts')->insert([
                'id' => self::deterministicAttemptId($record),
                'payment_id' => $record->paymentId,
                'attempt_reference' => $record->attemptReference,
                'status' => $record->status,
                'provider_key' => $record->providerKey,
                'provider_payment_reference' => $record->providerPaymentReference,
                'failure_reason_code' => $record->failureReasonCode,
                'started_at' => $this->databaseTimestamp($record->startedAt),
                'last_changed_at' => $this->databaseTimestamp($record->lastChangedAt),
                'position' => $record->position,
                'created_at' => $this->databaseTimestamp(new DateTimeImmutable),
                'updated_at' => $this->databaseTimestamp(new DateTimeImmutable),
            ]);
        }
    }

    private function paymentFromRow(stdClass $row): Payment
    {
        $record = new PaymentStorageRecord(
            id: $this->stringValue($row, 'id'),
            commercialOfferId: $this->stringValue($row, 'commercial_offer_id'),
            clinicRegistrationId: $this->stringValue($row, 'clinic_registration_id'),
            platformIdentityId: $this->nullableStringValue($row->platform_identity_id ?? null, 'platform_identity_id'),
            tenantId: $this->nullableStringValue($row->tenant_id ?? null, 'tenant_id'),
            amountMinor: $this->integerValue($row, 'amount_minor'),
            currency: $this->stringValue($row, 'currency'),
            idempotencyKey: $this->stringValue($row, 'idempotency_key'),
            status: $this->stringValue($row, 'status'),
            providerKey: $this->nullableStringValue($row->provider_key ?? null, 'provider_key'),
            providerPaymentReference: $this->nullableStringValue($row->provider_payment_reference ?? null, 'provider_payment_reference'),
            failureReasonCode: $this->nullableStringValue($row->failure_reason_code ?? null, 'failure_reason_code'),
            createdAt: $this->dateTimeValue($row->domain_created_at ?? null, 'domain_created_at'),
            lastChangedAt: $this->dateTimeValue($row->domain_last_changed_at ?? null, 'domain_last_changed_at'),
            version: $this->integerValue($row, 'version'),
        );

        $attemptRows = $this->connection->table('payment_attempts')->where('payment_id', $record->id)->orderBy('position')->get();
        $attempts = [];
        foreach ($attemptRows as $attemptRow) {
            $attempts[] = new PaymentAttemptStorageRecord(
                paymentId: $record->id,
                attemptReference: $this->stringValue($attemptRow, 'attempt_reference'),
                status: $this->stringValue($attemptRow, 'status'),
                providerKey: $this->stringValue($attemptRow, 'provider_key'),
                providerPaymentReference: $this->nullableStringValue($attemptRow->provider_payment_reference ?? null, 'provider_payment_reference'),
                failureReasonCode: $this->nullableStringValue($attemptRow->failure_reason_code ?? null, 'failure_reason_code'),
                startedAt: $this->dateTimeValue($attemptRow->started_at ?? null, 'started_at'),
                lastChangedAt: $this->dateTimeValue($attemptRow->last_changed_at ?? null, 'last_changed_at'),
                position: $this->integerValue($attemptRow, 'position'),
            );
        }

        return $this->mapper->toDomain($record, $attempts);
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

    private function integerValue(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidPaymentStorageStateException(sprintf('Storage field %s must be an integer.', $field));
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

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private static function deterministicAttemptId(PaymentAttemptStorageRecord $record): string
    {
        $hex = substr(hash('sha256', implode('|', [$record->paymentId, (string) $record->position, $record->attemptReference])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
