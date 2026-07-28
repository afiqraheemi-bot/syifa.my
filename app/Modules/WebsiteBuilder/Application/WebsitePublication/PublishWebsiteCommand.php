<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePublication;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class PublishWebsiteCommand
{
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
        public string $publicationId,
        public int $expectedWebsiteVersion,
        public int $expectedDraftVersion,
    ) {}
}
