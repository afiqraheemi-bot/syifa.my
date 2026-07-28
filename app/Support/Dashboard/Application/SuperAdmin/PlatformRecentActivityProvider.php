<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin;

use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class PlatformRecentActivityProvider implements DashboardSectionProviderInterface
{
    public function __construct(private PlatformDashboardReadInterface $dashboard) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('recentActivity', array_map(
            static fn ($activity): array => [
                'key' => $activity->id,
                'title' => ucwords(str_replace(['.', '_'], ' ', $activity->action)),
                'description' => 'Outcome: '.ucfirst($activity->outcome),
                'occurredAt' => $activity->occurredAt->format(DATE_ATOM),
                'occurredAtLabel' => $activity->occurredAt->format('j M Y, g:i A'),
            ],
            $this->dashboard->overview()->recentActivity,
        ));
    }
}
