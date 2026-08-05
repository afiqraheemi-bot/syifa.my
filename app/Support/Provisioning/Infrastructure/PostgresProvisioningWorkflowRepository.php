<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Support\Provisioning\Application\ClaimedProvisioningWorkflow;
use App\Support\Provisioning\Application\ProvisioningWorkflowData;
use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use stdClass;

final readonly class PostgresProvisioningWorkflowRepository implements ProvisioningWorkflowRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function register(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData
    {
        $timestamp = $event->occurredAt->format('Y-m-d H:i:s.uP');
        $this->connection->table('provisioning_workflows')->insertOrIgnore([
            'id' => $event->eventId,
            'source_event_id' => $event->eventId,
            'subscription_id' => $event->subscriptionId,
            'tenant_id' => $event->tenantId,
            'clinic_registration_id' => $event->clinicRegistrationId,
            'status' => 'pending',
            'current_step' => 'tenant_provisioning',
            'attempt_count' => 0,
            'occurred_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $row = $this->connection->table('provisioning_workflows')
            ->where('source_event_id', $event->eventId)
            ->first();
        if (! $row instanceof stdClass) {
            throw new RuntimeException('Provisioning workflow registration was not persisted.');
        }
        if ((string) $row->subscription_id !== $event->subscriptionId
            || (string) $row->tenant_id !== $event->tenantId
            || (string) $row->clinic_registration_id !== $event->clinicRegistrationId) {
            throw new RuntimeException('Provisioning workflow idempotency lineage conflicts with the source event.');
        }

        return $this->data($row);
    }

    public function findBySourceEvent(string $sourceEventId): ?ProvisioningWorkflowData
    {
        $row = $this->connection->table('provisioning_workflows')
            ->where('source_event_id', $sourceEventId)
            ->first();

        return $row instanceof stdClass ? $this->data($row) : null;
    }

    public function claimNext(DateTimeImmutable $now): ?ClaimedProvisioningWorkflow
    {
        return $this->connection->transaction(function () use ($now): ?ClaimedProvisioningWorkflow {
            $row = $this->connection->table('provisioning_workflows')
                ->where(function ($query) use ($now): void {
                    $query->whereIn('status', ['pending', 'retry_pending'])
                        ->where(function ($retry) use ($now): void {
                            $retry->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $this->timestamp($now));
                        });
                })
                ->orWhere(function ($query) use ($now): void {
                    $query->where('status', 'processing')->where('lease_expires_at', '<=', $this->timestamp($now));
                })
                ->orderBy('occurred_at')
                ->lock('for update skip locked')
                ->first();
            if (! $row instanceof stdClass) {
                return null;
            }

            $claimToken = $this->uuid();
            $this->connection->table('provisioning_workflows')
                ->where('id', (string) $row->id)
                ->update([
                    'status' => 'processing',
                    'claim_token' => $claimToken,
                    'lease_expires_at' => $this->timestamp($now->modify('+2 minutes')),
                    'next_attempt_at' => null,
                    'safe_failure_label' => null,
                    'attempt_count' => $this->connection->raw('attempt_count + 1'),
                    'updated_at' => $this->timestamp($now),
                ]);

            $claimed = $this->connection->table('provisioning_workflows')->where('id', (string) $row->id)->first();

            return $claimed instanceof stdClass
                ? new ClaimedProvisioningWorkflow($this->data($claimed), $claimToken)
                : throw new RuntimeException('Provisioning workflow claim was not persisted.');
        });
    }

    public function advance(string $workflowId, string $claimToken, string $nextStep, DateTimeImmutable $now): bool
    {
        return $this->release($workflowId, $claimToken, [
            'status' => 'pending',
            'current_step' => $nextStep,
            'next_attempt_at' => null,
            'safe_failure_label' => null,
        ], $now);
    }

    public function releaseForRetry(
        string $workflowId,
        string $claimToken,
        DateTimeImmutable $retryAt,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool {
        return $this->release($workflowId, $claimToken, [
            'status' => 'retry_pending',
            'next_attempt_at' => $this->timestamp($retryAt),
            'safe_failure_label' => substr($safeFailureLabel, 0, 120),
        ], $now);
    }

    public function deadLetter(
        string $workflowId,
        string $claimToken,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool {
        return $this->release($workflowId, $claimToken, [
            'status' => 'failed',
            'next_attempt_at' => null,
            'safe_failure_label' => substr($safeFailureLabel, 0, 120),
        ], $now);
    }

    public function complete(string $workflowId, string $claimToken, DateTimeImmutable $now): bool
    {
        return $this->release($workflowId, $claimToken, [
            'status' => 'completed',
            'current_step' => 'completed',
            'completed_at' => $this->timestamp($now),
            'next_attempt_at' => null,
            'safe_failure_label' => null,
        ], $now);
    }

    private function data(stdClass $row): ProvisioningWorkflowData
    {
        return new ProvisioningWorkflowData(
            (string) $row->id,
            (string) $row->source_event_id,
            (string) $row->subscription_id,
            (string) $row->tenant_id,
            (string) $row->clinic_registration_id,
            (string) $row->status,
            (string) $row->current_step,
            (int) $row->attempt_count,
            new DateTimeImmutable((string) $row->occurred_at),
        );
    }

    /** @param array<string, mixed> $values */
    private function release(string $workflowId, string $claimToken, array $values, DateTimeImmutable $now): bool
    {
        return $this->connection->table('provisioning_workflows')
            ->where('id', $workflowId)
            ->where('status', 'processing')
            ->where('claim_token', $claimToken)
            ->update($values + [
                'claim_token' => null,
                'lease_expires_at' => null,
                'updated_at' => $this->timestamp($now),
            ]) === 1;
    }

    private function timestamp(DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s.uP');
    }

    private function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        $hex[12] = '4';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
