<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\CustomDomain;

use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomain;

interface CustomDomainRepositoryInterface
{
    public function currentForWebsite(string $tenantId, string $websiteId): ?CustomDomain;

    public function findOwned(string $tenantId, string $domainId): ?CustomDomain;

    public function save(CustomDomain $domain): void;
}
