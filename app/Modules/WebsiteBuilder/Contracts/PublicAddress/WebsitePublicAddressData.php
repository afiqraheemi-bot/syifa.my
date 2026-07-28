<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicAddress;

final readonly class WebsitePublicAddressData
{
    public function __construct(
        public string $websiteId,
        public string $tenantId,
        public string $host,
        public string $url,
        public bool $active,
    ) {}

    public function status(): string
    {
        return $this->active ? 'live' : 'preparing';
    }
}
