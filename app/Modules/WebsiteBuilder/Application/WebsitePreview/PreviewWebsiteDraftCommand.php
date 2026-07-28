<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePreview;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class PreviewWebsiteDraftCommand
{
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
    ) {}
}
