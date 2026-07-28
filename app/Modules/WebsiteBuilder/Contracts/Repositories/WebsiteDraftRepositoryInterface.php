<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Repositories;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;

interface WebsiteDraftRepositoryInterface
{
    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent;

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent;
}
