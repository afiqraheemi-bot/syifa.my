<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class WebsiteDesignerDashboardOverviewProvider
{
    public function __construct(
        private WebsiteDesignerAssignmentsProvider $assignments,
        private WebsiteDesignerQuickActionsProvider $quickActions,
        private WebsiteDesignerRecentAssignmentsProvider $recentAssignments,
    ) {}

    /** @return array<string, mixed> */
    public function for(AuthorizationContext $context): array
    {
        $name = $context->name === null || trim($context->name) === ''
            ? 'Website Designer'
            : trim($context->name);

        return [
            'welcomeTitle' => "Welcome back, {$name}",
            'welcomeMessage' => 'Here is the latest view of your assigned onboarding work.',
            'summaries' => $this->assignments->provide($context)->data,
            'quickActions' => $this->quickActions->provide($context)->data,
            'recentAssignments' => $this->recentAssignments->provide($context)->data,
        ];
    }
}
