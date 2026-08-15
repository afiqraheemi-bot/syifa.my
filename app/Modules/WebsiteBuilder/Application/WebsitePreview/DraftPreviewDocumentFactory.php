<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePreview;

use App\Modules\WebsiteBuilder\Application\Delivery\BrandTokenResolver;
use App\Modules\WebsiteBuilder\Application\Delivery\ContactActionFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\NavigationFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoutePolicy;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\SeoDocumentHeadFactory;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class DraftPreviewDocumentFactory
{
    public function make(
        PublicWebsiteRenderModel $model,
        PublicSiteContext $context,
        ?PublicUrl $bookingDestination = null,
    ): PublicWebsiteDocument {
        $assetUrls = [];
        $assetDimensions = [];
        foreach ($model->assets as $asset) {
            // Draft previews must use the origin of the active request. A
            // fixed local asset origin such as `localhost` points back to the
            // viewing device when the preview is opened from a phone.
            $assetUrls[$asset->assetId] = new PublicUrl(
                $context->origin().'/assets/'.rawurlencode($asset->assetId).'?purpose=content',
            );
            $assetDimensions[$asset->assetId] = [$asset->width, $asset->height];
        }
        $routes = (new PublicRoutePolicy)->available($model, $context, false);
        $booking = $bookingDestination ?? $routes[PublicRoute::Booking->value] ?? $context->url();

        return new PublicWebsiteDocument(
            $model,
            $context,
            (new SeoDocumentHeadFactory)->make($model, $context, $context->url()),
            (new NavigationFactory)->make($model, $context, false),
            $assetUrls,
            $assetDimensions,
            [],
            (new ContactActionFactory)->make($model->footer),
            $booking,
            [],
            null,
            (new BrandTokenResolver)->resolve(
                $model->branding->primaryColor,
                $model->branding->secondaryColor,
            ),
        );
    }
}
