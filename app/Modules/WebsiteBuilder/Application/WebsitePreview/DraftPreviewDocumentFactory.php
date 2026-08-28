<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePreview;

use App\Modules\TenantManagement\Contracts\ClinicOwner\ClinicOwnerLocalePreferenceReadInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\BrandTokenResolver;
use App\Modules\WebsiteBuilder\Application\Delivery\ContactActionFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\NavigationFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicContentLanguage;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicRoutePolicy;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\SeoDocumentHeadFactory;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class DraftPreviewDocumentFactory
{
    public function __construct(private ClinicOwnerLocalePreferenceReadInterface $localePreferences) {}

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
        // Resolved the same way as the live document, so a designer/owner
        // previewing a draft sees the same language their real public site
        // would use once published.
        $ownerPreference = $context->tenantId === null ? null : $this->localePreferences->forTenant($context->tenantId);
        $language = PublicContentLanguage::resolve($model, $ownerPreference);

        return new PublicWebsiteDocument(
            $model,
            $context,
            (new SeoDocumentHeadFactory)->make($model, $context, $context->url(), $language),
            (new NavigationFactory)->make($model, $context, false, $language),
            $assetUrls,
            $assetDimensions,
            [],
            (new ContactActionFactory)->make($model->footer, language: $language),
            $booking,
            [],
            null,
            (new BrandTokenResolver)->resolve(
                $model->branding->primaryColor,
                $model->branding->secondaryColor,
            ),
            $language,
        );
    }
}
