<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class SuperAdminDashboardOverviewProvider
{
    public function __construct(
        private PlatformSummaryProvider $summaries,
        private PlatformQuickActionsProvider $quickActions,
        private PlatformRecentActivityProvider $recentActivity,
    ) {}

    /** @return array<string, mixed> */
    public function for(AuthorizationContext $context): array
    {
        $name = $context->name === null || trim($context->name) === '' ? 'Super Admin' : trim($context->name);

        return [
            'welcomeTitle' => "Welcome back, {$name}",
            'welcomeMessage' => 'Here is the current operational overview of SYIFA.my.',
            'summaries' => $this->summaries->provide($context)->data,
            'quickActions' => $this->quickActions->provide($context)->data,
            'recentActivity' => $this->recentActivity->provide($context)->data,
        ];
    }
}
