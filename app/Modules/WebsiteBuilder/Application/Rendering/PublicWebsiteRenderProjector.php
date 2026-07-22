<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\AboutSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\AssetRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BookingCtaSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BrandingRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BusinessHourRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ContactSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\DoctorRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\DoctorsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FaqEntryRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FaqSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\GalleryImageRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\GallerySectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeaderRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicationMetadataRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\SectionRenderContract;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\SeoRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServiceItemRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\TestimonialRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\TestimonialsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\WebsiteIdentityRenderModel;
use App\Modules\WebsiteBuilder\Domain\PublishedAssetSnapshot;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
use App\Modules\WebsiteBuilder\Domain\PublishedSectionContentSnapshot;
use App\Modules\WebsiteBuilder\Domain\PublishedWebsiteSnapshot;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use LogicException;

final readonly class PublicWebsiteRenderProjector
{
    public function project(PublishedWebsiteSnapshot $snapshot): PublicWebsiteRenderModel
    {
        $contact = $this->publishedContact($snapshot);
        $sections = [];
        foreach ($snapshot->sections as $index => $metadata) {
            $content = $snapshot->sectionContents[$index];
            if (! $metadata->enabled || ! $content->renderable) {
                continue;
            }
            $sections[] = $this->section($content);
        }
        $referencedAssets = $this->referencedAssetIds($snapshot, $sections);
        $assets = array_values(array_map(
            static fn (PublishedAssetSnapshot $asset): AssetRenderModel => new AssetRenderModel($asset->assetId->value, $asset->mimeType->value, $asset->width, $asset->height),
            array_filter($snapshot->assets, static fn (PublishedAssetSnapshot $asset): bool => isset($referencedAssets[$asset->assetId->value])),
        ));

        return new PublicWebsiteRenderModel(
            new WebsiteIdentityRenderModel($snapshot->websiteId->value, $snapshot->templateId->value),
            new BrandingRenderModel($snapshot->clinicName, $snapshot->tagline, $snapshot->primaryColor, $snapshot->secondaryColor, $snapshot->logoAssetId?->value, $snapshot->faviconAssetId?->value),
            new SeoRenderModel($snapshot->metaTitle, $snapshot->metaDescription, $snapshot->metaKeywords, $snapshot->canonicalUrl, $snapshot->robotsDirective->value, $snapshot->openGraphTitle, $snapshot->openGraphDescription, $snapshot->openGraphImageAssetId?->value, $snapshot->indexingEnabled),
            new HeaderRenderModel($snapshot->clinicName, $snapshot->tagline, $snapshot->logoAssetId?->value),
            new FooterRenderModel($snapshot->clinicName, $contact->email, $contact->phone, $contact->address, $contact->socialLinks, $this->businessHours($contact), $contact->whatsAppNumber, $contact->latitude, $contact->longitude),
            $sections,
            $assets,
            new PublicationMetadataRenderModel($snapshot->publicationId->value, $snapshot->publishedVersion, $snapshot->publishedAt),
        );
    }

    private function section(PublishedSectionContentSnapshot $snapshot): SectionRenderContract
    {
        $content = $snapshot->content;

        return match (true) {
            $content instanceof HeroSectionContent => new HeroSectionRenderModel($this->required($content->headline), $content->subheadline, $content->primaryCtaLabel, $content->primaryCtaTarget, $content->secondaryCtaLabel, $content->secondaryCtaTarget, $content->heroImageReference?->value),
            $content instanceof AboutSectionContent => new AboutSectionRenderModel($this->required($content->heading), $this->required($content->description), $content->imageReference?->value),
            $content instanceof ServicesSectionContent => new ServicesSectionRenderModel(array_map(
                static fn ($service): ServiceItemRenderModel => new ServiceItemRenderModel($service->serviceId, $service->displayName, $service->shortDescription, $service->displayOrder, $service->isFeatured),
                $snapshot->publishedServices,
            )),
            $content instanceof DoctorsSectionContent => new DoctorsSectionRenderModel(array_values(array_map(
                static fn ($profile): DoctorRenderModel => new DoctorRenderModel($profile->name, $profile->professionalTitle, $profile->photo?->value),
                array_filter($content->profiles, static fn ($profile): bool => $profile->visible),
            ))),
            $content instanceof TestimonialsSectionContent => new TestimonialsSectionRenderModel(array_values(array_map(
                static fn ($item): TestimonialRenderModel => new TestimonialRenderModel($item->quote, $item->authorName),
                array_filter($content->testimonials, static fn ($item): bool => $item->featured),
            ))),
            $content instanceof GallerySectionContent => new GallerySectionRenderModel(array_map(static fn ($order, $image): GalleryImageRenderModel => new GalleryImageRenderModel($image->imageReference->value, $image->altText, $image->caption, $order + 1, $image->decorative), array_keys($content->images), $content->images)),
            $content instanceof FaqSectionContent => new FaqSectionRenderModel(array_map(static fn ($entry): FaqEntryRenderModel => new FaqEntryRenderModel($entry->question, $entry->answer), $content->entries)),
            $content instanceof ContactSectionContent => $this->contact($snapshot->contactProjection),
            $content instanceof BookingCtaSectionContent => new BookingCtaSectionRenderModel($this->required($content->heading), $this->required($content->description), $this->required($content->buttonLabel)),
            default => throw new LogicException('Published Snapshot contains an unsupported Section content type.'),
        };
    }

    private function contact(?PublishedContactProjection $contact): ContactSectionRenderModel
    {
        if ($contact === null) {
            throw new LogicException('Published Snapshot is missing its Contact projection.');
        }

        return new ContactSectionRenderModel($contact->email, $contact->phone, $contact->address, $contact->socialLinks, $this->businessHours($contact), $contact->whatsAppNumber, $contact->latitude, $contact->longitude);
    }

    private function publishedContact(PublishedWebsiteSnapshot $snapshot): PublishedContactProjection
    {
        foreach ($snapshot->sectionContents as $section) {
            if ($section->contactProjection !== null) {
                return $section->contactProjection;
            }
        }

        throw new LogicException('Published Snapshot is missing its Contact projection.');
    }

    /** @return list<BusinessHourRenderModel> */
    private function businessHours(PublishedContactProjection $contact): array
    {
        return array_map(
            static fn ($hours): BusinessHourRenderModel => new BusinessHourRenderModel($hours->dayOfWeek, $hours->opensAt, $hours->closesAt),
            $contact->businessHours,
        );
    }

    private function required(?string $value): string
    {
        if ($value === null) {
            throw new LogicException('Renderable Published Snapshot content is incomplete.');
        }

        return $value;
    }

    /**
     * @param  list<SectionRenderContract>  $sections
     * @return array<string, true>
     */
    private function referencedAssetIds(PublishedWebsiteSnapshot $snapshot, array $sections): array
    {
        $ids = [];
        foreach ([$snapshot->logoAssetId?->value, $snapshot->faviconAssetId?->value, $snapshot->openGraphImageAssetId?->value] as $id) {
            if ($id !== null) {
                $ids[$id] = true;
            }
        }
        foreach ($sections as $section) {
            if ($section instanceof HeroSectionRenderModel && $section->heroImageAssetId !== null) {
                $ids[$section->heroImageAssetId] = true;
            } elseif ($section instanceof AboutSectionRenderModel && $section->imageAssetId !== null) {
                $ids[$section->imageAssetId] = true;
            } elseif ($section instanceof DoctorsSectionRenderModel) {
                foreach ($section->doctors as $doctor) {
                    if ($doctor->photoAssetId !== null) {
                        $ids[$doctor->photoAssetId] = true;
                    }
                }
            } elseif ($section instanceof GallerySectionRenderModel) {
                foreach ($section->images as $image) {
                    $ids[$image->assetId] = true;
                }
            }
        }

        return $ids;
    }
}
