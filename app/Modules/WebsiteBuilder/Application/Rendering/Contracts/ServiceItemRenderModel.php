<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class ServiceItemRenderModel
{
    public function __construct(
        public string $serviceId,
        public string $displayName,
        public ?string $shortDescription,
        public int $displayOrder,
        public bool $featured,
    ) {}
}
