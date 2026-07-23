<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;

final readonly class SubscriptionSummaryProvider implements DashboardSectionProviderInterface
{
    public function __construct(private SubscriptionSummaryReadInterface $subscriptions) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $subscription = $context->tenantId === null ? null : $this->subscriptions->summary($context->tenantId);

        return new DashboardSectionProjection('subscriptionSummary', $subscription === null
            ? [
                'key' => 'subscription',
                'label' => 'Subscription',
                'value' => 'Not available',
                'detail' => 'No subscription is available for this clinic.',
                'tone' => 'neutral',
            ]
            : [
                'key' => 'subscription',
                'label' => 'Subscription',
                'value' => ucfirst(str_replace('_', ' ', $subscription->status)),
                'detail' => sprintf('Current term ends %s.', $subscription->endsOn),
                'tone' => $subscription->status === 'active' ? 'positive' : 'neutral',
            ]);
    }
}
