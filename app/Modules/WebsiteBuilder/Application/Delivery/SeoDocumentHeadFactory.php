<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final readonly class SeoDocumentHeadFactory
{
    public function make(PublicWebsiteRenderModel $model, PublicSiteContext $context, PublicUrl $currentUrl): SeoDocumentHead
    {
        $structured = [
            '@context' => 'https://schema.org',
            '@type' => 'MedicalClinic',
            'name' => $model->branding->clinicName,
            'url' => $context->url()->value,
        ];
        if ($model->footer->contactPhone !== null) {
            $structured['telephone'] = $model->footer->contactPhone;
        }
        if ($model->footer->address !== null) {
            $structured['address'] = $model->footer->address;
        }

        return new SeoDocumentHead(
            $model->seo->metaTitle,
            $model->seo->metaDescription,
            $model->seo->robotsDirective,
            $currentUrl,
            $currentUrl,
            $model->seo->openGraphTitle,
            $model->seo->openGraphDescription,
            $structured,
        );
    }
}
