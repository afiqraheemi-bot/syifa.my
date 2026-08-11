<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\Subscription\ChangeSubscriptionPlanCommand;
use App\Modules\SubscriptionBilling\Contracts\Subscription\ChangeSubscriptionPlanInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class ChangeSubscriptionPlanService implements ChangeSubscriptionPlanInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private CommercialCatalogueQueryInterface $catalogue,
        private CapabilityDefinitionCatalogueQueryInterface $capabilities,
    ) {}

    public function change(ChangeSubscriptionPlanCommand $command): string
    {
        $offering = $this->catalogue->findPlanOffering($command->planOfferingId);
        if ($offering === null || $offering->status !== 'active'
            || $offering->effectiveStart > $command->occurredAt->format('Y-m-d')
            || ($offering->effectiveEnd !== null && $offering->effectiveEnd < $command->occurredAt->format('Y-m-d'))) {
            return 'offering_unavailable';
        }
        $plan = $this->catalogue->findPlan($offering->planId);
        $billingOption = $this->catalogue->findBillingOption($offering->billingOptionId);
        if ($plan === null || $plan->status !== 'active' || $billingOption === null || $billingOption->availability !== 'available') {
            return 'offering_unavailable';
        }

        $configured = config("subscription_packages.capability_profiles.{$offering->capabilityConfigurationReference}");
        $keys = [];
        foreach ($this->capabilities->listCapabilityDefinitions(new OffsetPaginationInput(1, 100))->items as $capability) {
            if ($capability->status === 'active' && (! is_array($configured) || in_array($capability->capabilityKey, $configured, true))) {
                $keys[] = $capability->capabilityKey;
            }
        }
        sort($keys, SORT_STRING);

        return $this->connection->transaction(function () use ($command, $offering, $keys): string {
            $subscription = $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->lockForUpdate()->first();
            if ($subscription === null) {
                return 'not_found';
            }
            if ((int) $subscription->version !== $command->expectedVersion) {
                return 'version_conflict';
            }
            if (! in_array((string) $subscription->status, ['active', 'renewal_due', 'reactivated'], true)) {
                return 'not_eligible';
            }
            if ((string) $subscription->plan_id === $offering->planId
                && (string) $subscription->billing_cycle_id === $offering->billingOptionId) {
                return 'same_plan';
            }

            $now = $command->occurredAt->format('Y-m-d H:i:s.uP');
            $this->connection->table('subscriptions')->where('id', $command->subscriptionId)->update([
                'plan_id' => $offering->planId,
                'billing_cycle_id' => $offering->billingOptionId,
                'amount_minor' => $offering->amountMinor,
                'currency' => $offering->currencyCode,
                'entitlement_configuration_version' => $offering->capabilityConfigurationReference,
                'entitlement_capabilities' => json_encode(array_values(array_unique($keys)), JSON_THROW_ON_ERROR),
                'last_changed_at' => $now,
                'version' => (int) $subscription->version + 1,
                'updated_at' => $now,
            ]);
            $this->connection->table('subscription_timeline_entries')->insert([
                'id' => (string) Str::uuid(), 'subscription_id' => $command->subscriptionId,
                'renewal_id' => null, 'event_type' => 'plan_changed', 'actor_id' => $command->actorId,
                'correlation_id' => $command->correlationId, 'occurred_at' => $now,
            ]);

            return 'plan_changed';
        });
    }
}
