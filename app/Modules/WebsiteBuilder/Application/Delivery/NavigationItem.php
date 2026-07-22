<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

final readonly class NavigationItem
{
    public function __construct(public PublicRoute $route, public string $label, public PublicUrl $url) {}
}
