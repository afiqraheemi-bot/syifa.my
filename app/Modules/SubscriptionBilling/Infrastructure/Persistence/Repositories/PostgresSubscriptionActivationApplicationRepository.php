<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplication;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionActivationApplicationPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionActivationApplicationStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final readonly class PostgresSubscriptionActivationApplicationRepository implements SubscriptionActivationApplicationRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private SubscriptionActivationApplicationPersistenceMapper $mapper) {}

    public function register(string $sourceEventId, string $paymentId, string $tenantId, DateTimeImmutable $now): SubscriptionActivationApplication
    {
        $timestamp = $this->timestamp($now);
        $this->connection->table('subscription_activation_applications')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'source_event_id' => $sourceEventId, 'payment_id' => $paymentId,
            'subscription_id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'status' => 'pending',
            'attempt_count' => 0, 'registered_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $row = $this->connection->table('subscription_activation_applications')->where('source_event_id', $sourceEventId)->first()
            ?? $this->connection->table('subscription_activation_applications')->where('payment_id', $paymentId)->first();

        $application = $this->map($row);
        if ($application->sourceEventId !== $sourceEventId || $application->paymentId !== $paymentId || $application->tenantId !== $tenantId) {
            throw new RuntimeException('Subscription activation idempotency identity conflict.');
        }

        return $application;
    }

    public function claim(string $applicationId, DateTimeImmutable $now, int $leaseSeconds): ?SubscriptionActivationApplication
    {
        $this->positiveSeconds($leaseSeconds);
        $token = (string) Str::uuid();
        $timestamp = $this->timestamp($now);
        $lease = $this->timestamp($now->modify('+'.$leaseSeconds.' seconds'));
        $rows = $this->connection->select(<<<'SQL'
            UPDATE subscription_activation_applications
            SET status='processing', processing_claim_token=?, processing_started_at=?, processing_lease_expires_at=?,
                attempt_count=attempt_count+1, last_attempt_at=?, next_attempt_at=NULL, updated_at=?
            WHERE id=? AND (status='pending' OR (status='retry_pending' AND next_attempt_at<=?) OR (status='processing' AND processing_lease_expires_at<=?))
            RETURNING *
            SQL, [$token, $timestamp, $lease, $timestamp, $timestamp, $applicationId, $timestamp, $timestamp]);

        return isset($rows[0]) ? $this->map($rows[0]) : null;
    }

    public function find(string $applicationId): ?SubscriptionActivationApplication
    {
        $row = $this->connection->table('subscription_activation_applications')->where('id', $applicationId)->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function complete(string $applicationId, string $claimToken, SubscriptionActivationApplicationStatus $status, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $now, ?DateTimeImmutable $nextAttemptAt = null): bool
    {
        return $this->connection->table('subscription_activation_applications')
            ->where('id', $applicationId)->where('processing_claim_token', $claimToken)->where('status', 'processing')
            ->where('processing_lease_expires_at', '>', $this->timestamp($now))
            ->update([
                'status' => $status->value, 'result_code' => $resultCode->value, 'processing_claim_token' => null,
                'processing_lease_expires_at' => null, 'next_attempt_at' => $nextAttemptAt === null ? null : $this->timestamp($nextAttemptAt),
                'completed_at' => $status === SubscriptionActivationApplicationStatus::RetryPending ? null : $this->timestamp($now),
                'updated_at' => $this->timestamp($now),
            ]) === 1;
    }

    public function markExhausted(string $applicationId, string $safeFailureLabel, DateTimeImmutable $now): bool
    {
        $timestamp = $this->timestamp($now);

        return $this->connection->table('subscription_activation_applications')
            ->where('id', $applicationId)
            ->whereIn('status', ['pending', 'processing', 'retry_pending'])
            ->update([
                'status' => 'exhausted', 'result_code' => 'exhausted', 'safe_failure_label' => $safeFailureLabel,
                'processing_claim_token' => null, 'processing_lease_expires_at' => null, 'next_attempt_at' => null,
                'completed_at' => $timestamp, 'updated_at' => $timestamp,
            ]) === 1;
    }

    private function map(mixed $row): SubscriptionActivationApplication
    {
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Subscription activation application storage is invalid.');
        }

        return $this->mapper->toDomain(new SubscriptionActivationApplicationStorageRecord(
            (string) $row->id, (string) $row->source_event_id, (string) $row->payment_id, (string) $row->subscription_id,
            (string) $row->tenant_id, (string) $row->status, (int) $row->attempt_count,
            isset($row->processing_claim_token) ? (string) $row->processing_claim_token : null,
            $this->date($row->processing_lease_expires_at ?? null), $this->date($row->next_attempt_at ?? null),
            isset($row->result_code) ? (string) $row->result_code : null,
        ));
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }

    private function positiveSeconds(int $seconds): void
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Lease duration must be positive.');
        }
    }
}
