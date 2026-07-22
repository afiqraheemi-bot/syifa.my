<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Repositories;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;

interface WebsiteRepositoryInterface
{
    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website;

    public function findByTenant(TenantId $tenantId): ?Website;

    public function save(Website $website): void;
}
