<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidSubscriptionStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use stdClass;

final readonly class PostgresSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private SubscriptionPersistenceMapper $mapper,
    ) {}

    public function find(SubscriptionId $id): ?Subscription
    {
        return $this->findWhere('id', $id->value);
    }

    public function findByTenantId(TenantId $tenantId): ?Subscription
    {
        return $this->findWhere('tenant_id', $tenantId->value);
    }

    public function findByPaymentId(PaymentId $paymentId): ?Subscription
    {
        return $this->findWhere('payment_id', $paymentId->value);
    }

    public function save(Subscription $subscription): void
    {
        if ($subscription->version() !== 0) {
            throw new InvalidSubscriptionStorageStateException('Increment 2 only permits inserting a new Subscription.');
        }

        $record = $this->mapper->toRecord($subscription);
        $now = $this->timestamp(new DateTimeImmutable);

        $this->connection->table('subscriptions')->insert([
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'clinic_registration_id' => $record->clinicRegistrationId,
            'payment_id' => $record->paymentId,
            'commercial_offer_id' => $record->commercialOfferId,
            'plan_id' => $record->planId,
            'billing_cycle_id' => $record->billingCycleId,
            'amount_minor' => $record->amountMinor,
            'currency' => $record->currency,
            'starts_on' => $record->startsOn,
            'ends_on' => $record->endsOn,
            'status' => $record->status,
            'entitlement_configuration_version' => $record->entitlementConfigurationVersion,
            'entitlement_status' => $record->entitlementStatus,
            'entitlement_capabilities' => json_encode($record->entitlementCapabilities, JSON_THROW_ON_ERROR),
            'created_at_domain' => $this->timestamp($record->createdAt),
            'last_changed_at' => $this->timestamp($record->lastChangedAt),
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subscription->synchronizePersistenceVersion(1);
    }

    private function findWhere(string $column, string $value): ?Subscription
    {
        $row = $this->connection->table('subscriptions')->where($column, $value)->first();

        return $row === null ? null : $this->mapper->toDomain($this->record($row));
    }

    private function record(stdClass $row): SubscriptionStorageRecord
    {
        return new SubscriptionStorageRecord(
            id: $this->string($row, 'id'),
            tenantId: $this->string($row, 'tenant_id'),
            clinicRegistrationId: $this->string($row, 'clinic_registration_id'),
            paymentId: $this->string($row, 'payment_id'),
            commercialOfferId: $this->string($row, 'commercial_offer_id'),
            planId: $this->string($row, 'plan_id'),
            billingCycleId: $this->string($row, 'billing_cycle_id'),
            amountMinor: $this->integer($row, 'amount_minor'),
            currency: $this->string($row, 'currency'),
            startsOn: $this->date($row, 'starts_on'),
            endsOn: $this->date($row, 'ends_on'),
            status: $this->string($row, 'status'),
            entitlementConfigurationVersion: $this->string($row, 'entitlement_configuration_version'),
            entitlementStatus: $this->string($row, 'entitlement_status'),
            entitlementCapabilities: $this->capabilities($row->entitlement_capabilities ?? null),
            createdAt: $this->dateTime($row, 'created_at_domain'),
            lastChangedAt: $this->dateTime($row, 'last_changed_at'),
            version: $this->integer($row, 'version'),
        );
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidSubscriptionStorageStateException(sprintf('Storage field %s must be a non-empty string.', $field));
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidSubscriptionStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function date(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        throw new InvalidSubscriptionStorageStateException(sprintf('Storage field %s must be a date.', $field));
    }

    private function dateTime(stdClass $row, string $field): DateTimeImmutable
    {
        $value = $row->{$field} ?? null;
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidSubscriptionStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    /** @return list<string> */
    private function capabilities(mixed $value): array
    {
        try {
            $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        } catch (JsonException $exception) {
            throw new InvalidSubscriptionStorageStateException('Stored entitlement capabilities must be valid JSON.', previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new InvalidSubscriptionStorageStateException('Stored entitlement capabilities must be an array.');
        }
        foreach ($decoded as $capability) {
            if (! is_string($capability)) {
                throw new InvalidSubscriptionStorageStateException('Stored entitlement capability must be a string.');
            }
        }

        return array_values($decoded);
    }

    private function timestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }
}
