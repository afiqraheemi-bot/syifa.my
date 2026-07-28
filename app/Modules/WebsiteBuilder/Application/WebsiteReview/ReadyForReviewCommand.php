<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteReview;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class ReadyForReviewCommand
{
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public string $websiteId,
        public int $expectedVersion,
    ) {}
}
