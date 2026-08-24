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
            '@id' => $context->url()->value.'#clinic',
            'name' => $model->branding->clinicName,
            'url' => $context->url()->value,
        ];
        if ($model->footer->contactPhone !== null) {
            $structured['telephone'] = $model->footer->contactPhone;
            $structured['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $model->footer->contactPhone,
                'contactType' => 'appointments',
            ];
        }
        if ($model->footer->address !== null) {
            $structured['address'] = $model->footer->address;
        }

        $canonicalUrl = $model->seo->canonicalUrl === null ? $currentUrl : new PublicUrl($model->seo->canonicalUrl);
        // "Indexing enabled" is presented to the Clinic Owner as a single
        // master switch ("Allow this website to be listed on Google"), so it
        // must override the separate robots directive field rather than only
        // affect the sitemap — otherwise turning it off gives a false sense
        // of privacy while the page still asserts "index,follow" itself.
        $robots = $model->seo->indexingEnabled ? $model->seo->robotsDirective : 'noindex,nofollow';

        // There is no dashboard field to set Open Graph title/description
        // independently from the main meta title/description - they always
        // read from meta here so a clinic's share-card text can't drift
        // stale relative to text the owner can actually see and edit.
        return new SeoDocumentHead(
            $model->seo->metaTitle,
            $model->seo->metaDescription,
            $robots,
            $canonicalUrl,
            $canonicalUrl,
            $model->seo->metaTitle,
            $model->seo->metaDescription,
            $structured,
        );
    }
}
