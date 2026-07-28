<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteDraft;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class SaveDraftWebsiteContent
{
    /** @param list<array<string, mixed>> $sections */
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
        public int $expectedVersion,
        public array $sections,
    ) {}
}
