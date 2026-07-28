<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Publication;

interface WebsitePublicationApprovalReadInterface
{
    public function isApproved(
        string $tenantId,
        string $websiteId,
        int $websiteVersion,
        int $draftVersion,
    ): bool;
}
