<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\DomainStatusProvider;

final readonly class ClinicOwnerDashboardOverviewProvider
{
    public function __construct(
        private ClinicSummaryProvider $clinic,
        private SubscriptionSummaryProvider $subscription,
        private BookingSummaryProvider $bookings,
        private DomainStatusProvider $websiteAddress,
        private QuickActionsProvider $quickActions,
        private RecentActivityProvider $recentActivity,
    ) {}

    /**
     * @return array{
     *     welcomeTitle: string,
     *     welcomeMessage: string,
     *     summaries: array{array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>},
     *     quickActions: array<array-key, mixed>,
     *     recentActivity: array<array-key, mixed>
     * }
     */
    public function for(AuthorizationContext $context): array
    {
        $name = $context->name === null || trim($context->name) === ''
            ? 'Clinic Owner'
            : trim($context->name);
        $clinic = $this->clinic->provide($context);
        $subscription = $this->subscription->provide($context);
        $bookings = $this->bookings->provide($context);
        $websiteAddress = $this->websiteAddress->provide($context);
        $quickActions = $this->quickActions->provide($context);
        $recentActivity = $this->recentActivity->provide($context);

        return [
            'welcomeTitle' => "Welcome back, {$name}",
            'welcomeMessage' => 'Here is the current overview of your SYIFA.my workspace.',
            'summaries' => [
                $clinic->data,
                $subscription->data,
                $bookings->data,
                [
                    'key' => 'website',
                    'label' => $websiteAddress->data['title'],
                    'value' => $websiteAddress->data['value'],
                    'detail' => $websiteAddress->data['detail'],
                    'tone' => $websiteAddress->data['tone'],
                    'url' => $websiteAddress->data['url'],
                    'actionLabel' => $websiteAddress->data['actionLabel'],
                ],
            ],
            'quickActions' => $quickActions->data,
            'recentActivity' => $recentActivity->data,
        ];
    }
}
