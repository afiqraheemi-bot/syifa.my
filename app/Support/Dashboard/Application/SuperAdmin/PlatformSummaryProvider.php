<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin;

use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class PlatformSummaryProvider implements DashboardSectionProviderInterface
{
    public function __construct(private PlatformDashboardReadInterface $dashboard) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $data = $this->dashboard->overview();

        return new DashboardSectionProjection('summaries', [
            $this->summary('tenants', 'Tenants', $data->tenants, 'Registered platform tenants.'),
            $this->summary('subscriptions', 'Active subscriptions', $data->activeSubscriptions, 'Subscriptions currently active.'),
            $this->summary('designers', 'Active Website Designers', $data->activeWebsiteDesigners, 'Active designer identities.'),
            $this->summary('onboarding', 'Onboarding pipeline', $data->onboardingPipeline, 'Jobs currently in the onboarding workflow.'),
            $this->summary('websites', 'Published websites', $data->publishedWebsites, 'Websites with a published snapshot.'),
            $this->summary('bookings', 'Booking platform', $data->bookings, 'Bookings recorded across the platform.'),
            [
                'key' => 'health',
                'label' => 'Platform health',
                'value' => $data->platformHealthy ? 'Operational' : 'Unavailable',
                'detail' => 'Database health probe status.',
                'tone' => $data->platformHealthy ? 'positive' : 'neutral',
            ],
        ]);
    }

    /** @return array{key: string, label: string, value: string, detail: string, tone: string} */
    private function summary(string $key, string $label, int $value, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => number_format($value),
            'detail' => $detail,
            'tone' => $value > 0 ? 'positive' : 'neutral',
        ];
    }
}
