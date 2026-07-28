<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteDraft;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class LoadDraftWebsiteContent
{
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
    ) {}
}
