<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\InvalidPublicDeliveryValueException;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class PublicWebsiteDocumentFactory
{
    public function __construct(private PublicAssetUrlResolverInterface $assets, private PlatformLegalContentProviderInterface $legal) {}

    public function make(PublicWebsiteRenderModel $model, PublicSiteContext $context): PublicWebsiteDocument
    {
        if ($context->websiteId !== null && $context->websiteId !== $model->website->websiteId) {
            throw new InvalidPublicDeliveryValueException('Public site context does not match the publication.');
        }
        $routes = (new PublicRoutePolicy)->available($model, $context);
        $booking = $routes[PublicRoute::Booking->value] ?? null;
        if (! $booking instanceof PublicUrl) {
            throw new InvalidPublicDeliveryValueException('Published Website does not expose Booking.');
        }
        $assetUrls = [];
        $assetDimensions = [];
        foreach ($model->assets as $asset) {
            $assetUrls[$asset->assetId] = $this->assets->resolve($asset->assetId, PublicAssetPurpose::Content);
            $assetDimensions[$asset->assetId] = [$asset->width, $asset->height];
        }
        $legalUrls = [];
        foreach ([PublicRoute::Privacy, PublicRoute::Terms] as $legalRoute) {
            if ($this->legal->find($legalRoute) !== null) {
                $legalUrls[$legalRoute->value] = $routes[$legalRoute->value];
            }
        }
        $sitemap = array_values(array_filter(
            $routes,
            static fn (PublicUrl $url, string $name): bool => (! in_array($name, [PublicRoute::Privacy->value, PublicRoute::Terms->value], true) || isset($legalUrls[$name])) && ! str_contains($url->value, '#'),
            ARRAY_FILTER_USE_BOTH,
        ));

        return new PublicWebsiteDocument(
            $model,
            $context,
            (new SeoDocumentHeadFactory)->make($model, $context, $context->url()),
            (new NavigationFactory)->make($model, $context),
            $assetUrls,
            $assetDimensions,
            $legalUrls,
            (new ContactActionFactory)->make($model->footer),
            $booking,
            $sitemap,
        );
    }
}
