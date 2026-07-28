<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteQuickActionsProvider implements DashboardSectionProviderInterface
{
    public function __construct(private ?string $editUrl = null) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            ['key' => 'edit', 'label' => 'Edit website', 'description' => 'Update your clinic website content.', 'href' => $this->editUrl ?? route('dashboard.website.content'), 'available' => true],
        ]);
    }
}
