<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Queue;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
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
                'navigation' => [
                    (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                    (new DashboardNavigationItem('onboarding', 'Onboarding', route('dashboard.onboarding'), true))->toArray(),
                ],
                'breadcrumbs' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                    ['key' => 'onboarding', 'label' => 'Onboarding'],
                ],
                'pageTitle' => 'Onboarding queue',
                'pageDescription' => 'Review the progress of onboarding jobs assigned to you.',
                'identityName' => $context->name,
                'contextLabel' => 'Website Designer workspace',
                'onboardingQueue' => $this->queue
                    ->provide($context, WebsiteDesignerQueueCriteria::fromInput($query))
                    ->data,
            ],
        );
    }
}
