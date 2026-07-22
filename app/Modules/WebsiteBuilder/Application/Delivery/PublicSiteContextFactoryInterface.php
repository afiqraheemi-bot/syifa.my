<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

interface PublicSiteContextFactoryInterface
{
    public function forHost(string $host): ?PublicSiteContext;
}
