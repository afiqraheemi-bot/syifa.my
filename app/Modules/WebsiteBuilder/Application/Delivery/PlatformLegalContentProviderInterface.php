<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

interface PlatformLegalContentProviderInterface
{
    public function find(PublicRoute $route): ?PlatformLegalDocument;
}
