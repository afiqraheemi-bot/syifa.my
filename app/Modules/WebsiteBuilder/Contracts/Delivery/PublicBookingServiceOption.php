<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

final readonly class PublicBookingServiceOption
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $featured,
    ) {}
}
