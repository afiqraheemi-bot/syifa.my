<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class WebsiteIdentityRenderModel
{
    public function __construct(public string $websiteId, public string $templateId) {}
}
