<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class PublicWebsiteDocument
{
    /**
     * @param  list<NavigationItem>  $navigation
     * @param  array<string, PublicUrl>  $assetUrls
     * @param  array<string, array{?int, ?int}>  $assetDimensions
     * @param  array<string, PublicUrl>  $legalUrls
     * @param  list<PublicUrl>  $sitemapUrls
     */
    public function __construct(
        public PublicWebsiteRenderModel $website,
        public PublicSiteContext $context,
        public SeoDocumentHead $head,
        public array $navigation,
        public array $assetUrls,
        public array $assetDimensions,
        public array $legalUrls,
        public ContactActionSet $contactActions,
        public PublicUrl $bookingDestination,
        public array $sitemapUrls,
        public ?string $todayHoursLabel,
        public BrandTokens $brandTokens,
        public string $language,
    ) {}
}
