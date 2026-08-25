<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Queue;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardPageView;
use App\Support\Dashboard\Application\WebsiteDesignerDashboardNavigation;
use LogicException;

final readonly class WebsiteDesignerOnboardingQueuePage
{
    public function __construct(private WebsiteDesignerQueueProvider $queue) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Website Designer dashboard context was not established.');
        }

        return new DashboardPageView(
            'PlatformAdministration/Onboarding/WebsiteDesignerOnboardingQueue',
            [
                'navigation' => WebsiteDesignerDashboardNavigation::items('onboarding'),
                'breadcrumbs' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                    ['key' => 'onboarding', 'label' => 'Onboarding'],
                ],
                'pageTitle' => 'Onboarding klinik',
                'pageDescription' => 'Semak kemajuan dan teruskan tugasan klinik yang diberikan kepada anda.',
                'identityName' => $context->name,
                'contextLabel' => 'Website Designer workspace',
                'onboardingQueue' => $this->queue
                    ->provide($context, WebsiteDesignerQueueCriteria::fromInput($query))
                    ->data,
            ],
        );
    }
}
