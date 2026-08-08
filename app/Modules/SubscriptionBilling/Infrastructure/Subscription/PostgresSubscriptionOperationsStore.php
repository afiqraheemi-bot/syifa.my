<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription;

use App\Modules\AcquisitionOffer\Contracts\Renewal\PreparedRenewalOffer;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\SubscriptionOperationsStoreInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class PostgresSubscriptionOperationsStore implements SubscriptionOperationsStoreInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function createRenewal(string $renewalId, ManualRenewSubscriptionCommand $command, PreparedRenewalOffer $offer): RenewalOperationResult
    {
        return $this->connection->transaction(function () use ($renewalId, $command, $offer): RenewalOperationResult {
            $existing = $this->connection->table('subscription_renewals')
                ->where('subscription_id', $command->subscriptionId)
                ->where('request_idempotency_key', $command->idempotencyKey)
                ->first();
            if ($existing !== null) {
                return new RenewalOperationResult('already_accepted', (string) $existing->id);
            }
            $subscription = $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->lockForUpdate()->first();
            if ($subscription === null) {
                return new RenewalOperationResult('not_found');
            }
            if ((int) $subscription->version !== $command->expectedVersion) {
                return new RenewalOperationResult('version_conflict');
            }
            if ((string) $subscription->status !== 'renewal_due') {
                return new RenewalOperationResult('not_eligible');
            }
            $now = $command->occurredAt->format('Y-m-d H:i:s.uP');
            $this->connection->table('subscription_renewals')->insert([
                'id' => $renewalId, 'subscription_id' => $command->subscriptionId,
                'commercial_offer_id' => $offer->commercialOfferId,
                'request_idempotency_key' => $command->idempotencyKey,
                'mode' => 'manual', 'status' => 'requested', 'plan_id' => $offer->planId,
                'billing_cycle_id' => $offer->billingCycleId, 'amount_minor' => $offer->amountMinor,
                'currency' => $offer->currency, 'starts_on' => $offer->startsOn, 'ends_on' => $offer->endsOn,
                'requested_actor_id' => $command->actorId, 'requested_at' => $now,
                'last_changed_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->update([
                'last_changed_at' => $now,
                'version' => (int) $subscription->version + 1,
                'updated_at' => $now,
            ]);
            $this->timeline($command->subscriptionId, $renewalId, 'renewal_requested', $command->actorId, $command->correlationId, $now);

            return new RenewalOperationResult('accepted', $renewalId);
        });
    }

    public function changeAutoRenew(AutoRenewCommand $command, string $status, string $eventType): AutoRenewOperationResult
    {
        return $this->connection->transaction(function () use ($command, $status, $eventType): AutoRenewOperationResult {
            $row = $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->lockForUpdate()->first();
            if ($row === null) {
                return new AutoRenewOperationResult('not_found', 0);
            }
            if ((int) $row->version !== $command->expectedVersion) {
                return new AutoRenewOperationResult('version_conflict', (int) $row->version);
            }
            if ((string) $row->auto_renew_status === $status) {
                return new AutoRenewOperationResult($status === 'enabled' ? 'already_enabled' : 'already_cancelled', (int) $row->version);
            }
            $version = (int) $row->version + 1;
            $now = $command->occurredAt->format('Y-m-d H:i:s.uP');
            $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->update([
                'auto_renew_status' => $status, 'auto_renew_changed_at' => $now,
                'last_changed_at' => $now, 'version' => $version, 'updated_at' => $now,
            ]);
            $this->timeline($command->subscriptionId, null, $eventType, $command->actorId, $command->correlationId, $now);

            return new AutoRenewOperationResult($status, $version);
        });
    }

    private function timeline(string $subscriptionId, ?string $renewalId, string $eventType, string $actorId, string $correlationId, string $occurredAt): void
    {
        $this->connection->table('subscription_timeline_entries')->insert([
            'id' => (string) Str::uuid(), 'subscription_id' => $subscriptionId,
            'renewal_id' => $renewalId, 'event_type' => $eventType, 'actor_id' => $actorId,
            'correlation_id' => $correlationId, 'occurred_at' => $occurredAt,
        ]);
    }
}
