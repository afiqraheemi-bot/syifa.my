<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class SeoRenderModel
{
    public function __construct(public string $metaTitle, public string $metaDescription, public ?string $metaKeywords, public ?string $canonicalUrl, public string $robotsDirective, public string $openGraphTitle, public string $openGraphDescription, public ?string $openGraphImageAssetId, public bool $indexingEnabled) {}
}
