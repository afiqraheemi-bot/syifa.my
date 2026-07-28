<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
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
}
