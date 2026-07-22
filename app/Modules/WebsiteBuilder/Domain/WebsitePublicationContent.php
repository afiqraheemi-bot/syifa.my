<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;

final readonly class WebsitePublicationContent
{
    /**
     * @param  list<WebsiteSectionContentInterface>  $contents
     * @param  array<string, bool>  $renderabilityBySectionId
     * @param  list<ServicePublicationProjection>  $serviceProjections
     */
    public function __construct(
        public array $contents,
        public array $renderabilityBySectionId,
        public array $serviceProjections = [],
        public ?PublishedContactProjection $contactProjection = null,
    ) {
        $ids = array_map(static fn (WebsiteSectionContentInterface $content): string => $content->sectionId()->value, $contents);
        if (count($contents) !== 9 || count(array_unique($ids)) !== 9 || count($renderabilityBySectionId) !== 9 || array_diff($ids, array_keys($renderabilityBySectionId)) !== [] || array_diff(array_keys($renderabilityBySectionId), $ids) !== []) {
            throw new InvalidWebsiteValueException('Publication content must contain exactly one value and renderability result for every governed Section.');
        }
        $serviceIds = array_map(static fn (ServicePublicationProjection $service): string => $service->serviceId, $serviceProjections);
        if (count(array_unique($serviceIds)) !== count($serviceIds)) {
            throw new InvalidWebsiteValueException('Service publication projections must be unique.');
        }
    }

    /** @return list<PublishedSectionContentSnapshot> */
    public function capture(WebsiteSectionCollection $sections, PublicationId $publicationId, WebsiteId $websiteId, int $publishedVersion, WebsiteBranding $branding, DateTimeImmutable $at): array
    {
        $byId = [];
        foreach ($this->contents as $content) {
            $byId[$content->sectionId()->value] = $content;
        }
        $snapshots = [];
        foreach ($sections->sections() as $section) {
            $content = $byId[$section->id->value] ?? null;
            $renderable = $this->renderabilityBySectionId[$section->id->value] ?? null;
            if (! $content instanceof WebsiteSectionContentInterface || $content->sectionType() !== $section->type || ! is_bool($renderable) || ($section->enabled() && ! $renderable)) {
                throw new InvalidWebsiteValueException('Published Section content does not match the governed Section or its renderability requirement.');
            }
            if ($renderable && ! $this->canBeRenderable($content, $branding)) {
                throw new InvalidWebsiteValueException('Published Section renderability evidence contradicts its immutable content.');
            }
            $contact = $content instanceof ContactSectionContent ? $this->contactProjection : null;
            if ($content instanceof ContactSectionContent && (! $contact instanceof PublishedContactProjection || ($section->enabled() && ! $contact->hasMinimumContact()))) {
                throw new InvalidWebsiteValueException('Published Contact projection does not satisfy Contact renderability.');
            }
            if ($content instanceof GallerySectionContent && $section->enabled()) {
                foreach ($content->images as $image) {
                    if (! $image->decorative && $image->altText === null) {
                        throw new InvalidWebsiteValueException('Informative Gallery images require approved alternative text.');
                    }
                }
            }
            $publishedServices = $content instanceof ServicesSectionContent ? $this->publishedServices($content, $section->enabled()) : [];
            $canonical = $this->canonical($content, $contact, $publishedServices);
            $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $snapshots[] = new PublishedSectionContentSnapshot($publicationId, $websiteId, $section->id, $section->type, $publishedVersion, $content, hash('sha256', $encoded), $renderable, $at, 1, $contact, $publishedServices);
        }

        return $snapshots;
    }

    /** @return list<PublishedServiceItem> */
    private function publishedServices(ServicesSectionContent $content, bool $enabled): array
    {
        $byId = [];
        foreach ($this->serviceProjections as $projection) {
            $byId[$projection->serviceId] = $projection;
        }
        $published = [];
        foreach ($content->items as $item) {
            $projection = $byId[$item->serviceId] ?? null;
            if (! $projection instanceof ServicePublicationProjection) {
                if ($enabled) {
                    throw new InvalidWebsiteValueException('Enabled Services require complete immutable Service presentation.');
                }

                continue;
            }
            $published[] = new PublishedServiceItem($projection->serviceId, $projection->displayName, $projection->shortDescription, $item->displayOrder, $item->isFeatured);
        }

        return $published;
    }

    /** @return list<array{AssetId, AssetUsage}> */
    public function assetReferences(): array
    {
        $references = [];
        foreach ($this->contents as $content) {
            if ($content instanceof HeroSectionContent && $content->heroImageReference !== null) {
                $references[] = [$content->heroImageReference, AssetUsage::ContentImage];
            } elseif ($content instanceof AboutSectionContent && $content->imageReference !== null) {
                $references[] = [$content->imageReference, AssetUsage::ContentImage];
            } elseif ($content instanceof DoctorsSectionContent) {
                foreach ($content->profiles as $profile) {
                    if ($profile->photo !== null) {
                        $references[] = [$profile->photo, AssetUsage::DoctorPhoto];
                    }
                }
            } elseif ($content instanceof GallerySectionContent) {
                foreach ($content->images as $image) {
                    $references[] = [$image->imageReference, AssetUsage::ContentImage];
                }
            }
        }

        return $references;
    }

    private function canBeRenderable(WebsiteSectionContentInterface $content, WebsiteBranding $branding): bool
    {
        return match (true) {
            $content instanceof HeroSectionContent => $content->isRenderable(),
            $content instanceof AboutSectionContent => $content->isRenderable(),
            $content instanceof ServicesSectionContent => $content->serviceReferences() !== [],
            $content instanceof DoctorsSectionContent => $content->isRenderable(),
            $content instanceof TestimonialsSectionContent => $content->isRenderable(),
            $content instanceof GallerySectionContent => $content->isRenderable(),
            $content instanceof FaqSectionContent => $content->isRenderable(),
            $content instanceof ContactSectionContent => $content->isRenderable($branding),
            $content instanceof BookingCtaSectionContent => $content->isRenderable(true),
            default => false,
        };
    }

    /**
     * @param  list<PublishedServiceItem>  $publishedServices
     * @return array<string, mixed>
     */
    private function canonical(WebsiteSectionContentInterface $content, ?PublishedContactProjection $contact, array $publishedServices): array
    {
        return match (true) {
            $content instanceof HeroSectionContent => ['type' => SectionType::Hero->value, 'headline' => $content->headline, 'subheadline' => $content->subheadline, 'primaryCtaLabel' => $content->primaryCtaLabel, 'primaryCtaTarget' => $content->primaryCtaTarget, 'secondaryCtaLabel' => $content->secondaryCtaLabel, 'secondaryCtaTarget' => $content->secondaryCtaTarget, 'heroImageReference' => $content->heroImageReference?->value],
            $content instanceof AboutSectionContent => ['type' => SectionType::About->value, 'heading' => $content->heading, 'description' => $content->description, 'imageReference' => $content->imageReference?->value],
            $content instanceof ServicesSectionContent => ['type' => SectionType::Services->value, 'services' => array_map(static fn (PublishedServiceItem $item): array => ['serviceId' => $item->serviceId, 'displayName' => $item->displayName, 'shortDescription' => $item->shortDescription, 'displayOrder' => $item->displayOrder, 'isFeatured' => $item->isFeatured], $publishedServices)],
            $content instanceof DoctorsSectionContent => ['type' => SectionType::Doctors->value, 'profiles' => array_map(static fn ($profile): array => ['id' => $profile->id, 'name' => $profile->name, 'professionalTitle' => $profile->professionalTitle, 'visible' => $profile->visible, 'photo' => $profile->photo?->value], $content->profiles)],
            $content instanceof TestimonialsSectionContent => ['type' => SectionType::Testimonials->value, 'testimonials' => array_map(static fn ($item): array => ['id' => $item->id, 'quote' => $item->quote, 'authorName' => $item->authorName, 'featured' => $item->featured], $content->testimonials)],
            $content instanceof GallerySectionContent => ['type' => SectionType::Gallery->value, 'images' => array_map(static fn ($image, int $order): array => ['id' => $image->id, 'assetId' => $image->imageReference->value, 'altText' => $image->altText, 'caption' => $image->caption, 'decorative' => $image->decorative, 'displayOrder' => $order + 1], $content->images, array_keys($content->images))],
            $content instanceof FaqSectionContent => ['type' => SectionType::Faq->value, 'entries' => array_map(static fn ($entry): array => ['id' => $entry->id, 'question' => $entry->question, 'answer' => $entry->answer], $content->entries)],
            $content instanceof ContactSectionContent && $contact !== null => ['type' => SectionType::Contact->value, 'email' => $contact->email, 'phone' => $contact->phone, 'address' => $contact->address, 'socialLinks' => $contact->socialLinks, 'businessHours' => array_map(static fn (PublishedBusinessHour $hour): array => [$hour->dayOfWeek, $hour->opensAt, $hour->closesAt], $contact->businessHours), 'whatsAppNumber' => $contact->whatsAppNumber, 'latitude' => $contact->latitude, 'longitude' => $contact->longitude],
            $content instanceof BookingCtaSectionContent => ['type' => SectionType::BookingCta->value, 'heading' => $content->heading, 'description' => $content->description, 'buttonLabel' => $content->buttonLabel],
            default => throw new InvalidWebsiteValueException('Unsupported published Section content type.'),
        };
    }
}
