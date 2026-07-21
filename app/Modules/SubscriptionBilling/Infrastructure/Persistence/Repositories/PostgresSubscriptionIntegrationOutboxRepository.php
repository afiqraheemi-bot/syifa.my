<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxClaim;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionIntegrationOutboxPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionIntegrationOutboxStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class PostgresSubscriptionIntegrationOutboxRepository implements SubscriptionIntegrationOutboxRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private SubscriptionIntegrationOutboxPersistenceMapper $mapper) {}

    public function add(SubscriptionActivatedIntegrationEvent $event): void
    {
        $record = $this->mapper->toRecord($event);
        $timestamp = $this->timestamp($record->occurredAt);
        $this->connection->table('subscription_integration_outbox')->insertOrIgnore([
            'id' => $record->id, 'event_type' => $record->eventType, 'event_version' => $record->eventVersion,
            'subscription_id' => $record->subscriptionId, 'payload' => json_encode($record->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $timestamp, 'publish_attempt_count' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }

    public function pending(DateTimeImmutable $availableAt, int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }
        $timestamp = $this->timestamp($availableAt);
        $rows = $this->connection->table('subscription_integration_outbox')->whereNull('published_at')
            ->where(static fn ($query) => $query->whereNull('next_publish_attempt_at')->orWhere('next_publish_attempt_at', '<=', $timestamp))
            ->where(static fn ($query) => $query->whereNull('publish_claim_token')->orWhere('publish_lease_expires_at', '<=', $timestamp))
            ->orderBy('occurred_at')->limit($limit)->get();

        return array_values(array_map(
            fn (stdClass $row): SubscriptionActivatedIntegrationEvent => $this->mapper->toEvent($this->record($row)),
            $rows->all(),
        ));
    }

    public function claimNext(DateTimeImmutable $now, int $leaseSeconds = 120): ?SubscriptionIntegrationOutboxClaim
    {
        $token = (string) Str::uuid();
        $timestamp = $this->timestamp($now);
        $lease = $this->timestamp($now->modify('+'.$leaseSeconds.' seconds'));
        $rows = $this->connection->select(<<<'SQL'
            UPDATE subscription_integration_outbox SET publish_claim_token=?, publish_lease_expires_at=?,
                publish_attempt_count=publish_attempt_count+1, updated_at=?
            WHERE id=(SELECT id FROM subscription_integration_outbox WHERE published_at IS NULL
                AND (next_publish_attempt_at IS NULL OR next_publish_attempt_at<=?)
                AND (publish_claim_token IS NULL OR publish_lease_expires_at<=?)
                ORDER BY occurred_at FOR UPDATE SKIP LOCKED LIMIT 1)
            RETURNING *
            SQL, [$token, $lease, $timestamp, $timestamp, $timestamp]);
        $row = $rows[0] ?? null;

        return $row instanceof stdClass ? $this->mapper->toClaim($this->record($row)) : null;
    }

    public function completeDispatch(string $eventId, string $leaseToken, DateTimeImmutable $dispatchedAt): bool
    {
        $timestamp = $this->timestamp($dispatchedAt);

        return $this->connection->table('subscription_integration_outbox')->where('id', $eventId)
            ->where('publish_claim_token', $leaseToken)->whereNull('published_at')->update([
                'published_at' => $timestamp, 'publish_claim_token' => null, 'publish_lease_expires_at' => null,
                'next_publish_attempt_at' => null, 'safe_failure_label' => null, 'updated_at' => $timestamp,
            ]) === 1;
    }

    public function releaseForRetry(string $eventId, string $leaseToken, DateTimeImmutable $nextRetryAt, string $safeFailureLabel, DateTimeImmutable $now): bool
    {
        if ($safeFailureLabel === '' || mb_strlen($safeFailureLabel) > 120) {
            throw new \InvalidArgumentException('Safe failure label is required and limited to 120 characters.');
        }

        return $this->connection->table('subscription_integration_outbox')->where('id', $eventId)
            ->where('publish_claim_token', $leaseToken)->whereNull('published_at')->update([
                'publish_claim_token' => null, 'publish_lease_expires_at' => null,
                'next_publish_attempt_at' => $this->timestamp($nextRetryAt), 'safe_failure_label' => $safeFailureLabel,
                'updated_at' => $this->timestamp($now),
            ]) === 1;
    }

    private function record(stdClass $row): SubscriptionIntegrationOutboxStorageRecord
    {
        try {
            $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored Subscription outbox payload is malformed.', 0, $exception);
        }
        if (! is_array($payload)) {
            throw new RuntimeException('Stored Subscription outbox payload must be an object.');
        }
        foreach ($payload as $value) {
            if (! is_int($value) && ! is_string($value)) {
                throw new RuntimeException('Stored Subscription outbox payload contains an unsupported value.');
            }
        }

        return new SubscriptionIntegrationOutboxStorageRecord(
            (string) $row->id, (string) $row->event_type, (int) $row->event_version, (string) $row->subscription_id, $payload,
            new DateTimeImmutable((string) $row->occurred_at), isset($row->published_at) ? new DateTimeImmutable((string) $row->published_at) : null,
            isset($row->publish_claim_token) ? (string) $row->publish_claim_token : null,
            isset($row->publish_lease_expires_at) ? new DateTimeImmutable((string) $row->publish_lease_expires_at) : null,
            (int) $row->publish_attempt_count, isset($row->next_publish_attempt_at) ? new DateTimeImmutable((string) $row->next_publish_attempt_at) : null,
            isset($row->safe_failure_label) ? (string) $row->safe_failure_label : null,
        );
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
