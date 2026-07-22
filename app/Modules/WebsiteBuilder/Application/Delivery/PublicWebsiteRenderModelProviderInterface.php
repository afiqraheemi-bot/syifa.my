<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

interface PublicWebsiteRenderModelProviderInterface
{
    public function find(PublicSiteContext $context): ?PublicWebsiteRenderModel;
}
