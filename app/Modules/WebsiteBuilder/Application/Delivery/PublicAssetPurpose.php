<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

enum PublicAssetPurpose: string
{
    case Content = 'content';
    case Logo = 'logo';
    case Favicon = 'favicon';
    case OpenGraph = 'open_graph';
}
