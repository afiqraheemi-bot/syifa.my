<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteDesignerQuickActionsProvider implements DashboardSectionProviderInterface
{
    public function __construct(
        private ?string $assignmentsUrl = null,
        private ?string $setupUrl = null,
        private ?string $reviewUrl = null,
    ) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            [
                'key' => 'view-assignments',
                'label' => 'View assignments',
                'description' => 'Open your active onboarding queue.',
                'href' => $this->assignmentsUrl ?? route('dashboard.onboarding'),
                'available' => true,
            ],
            [
                'key' => 'continue-setup',
                'label' => 'Continue website setup',
                'description' => 'Choose an assigned clinic and continue its website setup.',
                'href' => $this->setupUrl ?? route('dashboard.onboarding', ['status' => 'website_setup']),
                'available' => true,
            ],
            [
                'key' => 'review-projects',
                'label' => 'Projects in review',
                'description' => 'Open assigned websites currently in the review stage.',
                'href' => $this->reviewUrl ?? route('dashboard.onboarding', ['status' => 'review_attention']),
                'available' => true,
            ],
        ]);
    }
}
