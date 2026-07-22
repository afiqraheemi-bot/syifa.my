<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class PublicWebsiteRenderModel
{
    /**
     * @param  list<SectionRenderContract>  $sections
     * @param  list<AssetRenderModel>  $assets
     */
    public function __construct(public WebsiteIdentityRenderModel $website, public BrandingRenderModel $branding, public SeoRenderModel $seo, public HeaderRenderModel $header, public FooterRenderModel $footer, public array $sections, public array $assets, public PublicationMetadataRenderModel $publication) {}
}
