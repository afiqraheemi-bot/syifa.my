<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantOverview;

final readonly class TenantOverviewData
{
    public function __construct(
        public string $tenantId,
        public ?string $clinicName,
        public ?string $ownerName,
        public ?string $ownerEmail,
        public string $tenantStatus,
        public ?string $subscriptionStatus,
        public bool $websitePublished,
        public ?string $websiteDesignerName,
    ) {}
}
