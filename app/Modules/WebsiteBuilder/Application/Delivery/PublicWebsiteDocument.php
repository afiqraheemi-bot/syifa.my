<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class PublicWebsiteDocument
{
    /**
     * @param  list<NavigationItem>  $navigation
     * @param  array<string, PublicUrl>  $assetUrls
     * @param  list<PublicUrl>  $sitemapUrls
     */
    public function __construct(
        public PublicWebsiteRenderModel $website,
        public PublicSiteContext $context,
        public SeoDocumentHead $head,
        public array $navigation,
        public array $assetUrls,
        public ContactActionSet $contactActions,
        public PublicUrl $bookingDestination,
        public array $sitemapUrls,
    ) {}
}
