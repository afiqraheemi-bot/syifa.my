<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePreview;

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
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetStatus;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\DayOfWeek;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use DateTimeImmutable;
use RuntimeException;

final readonly class PreviewWebsiteDraftService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsiteDraftRepositoryInterface $drafts,
        private ClinicRepositoryInterface $clinics,
        private PublicBookingFormConfigurationReaderInterface $booking,
        private WebsiteAuthorization $authorization,
    ) {}

    public function handle(PreviewWebsiteDraftCommand $command): PublicWebsiteRenderModel
    {
        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);
        $website = $this->websites->findById($tenantId, $websiteId)
            ?? throw new RuntimeException('Website was not found in the authorized scope.');
        $draft = $this->drafts->find($tenantId, $websiteId)
            ?? throw new RuntimeException('Website Draft was not found in the authorized scope.');
        $clinic = $this->clinics->findByTenantId($tenantId)
            ?? throw new RuntimeException('Clinic was not found in the authorized scope.');
        $booking = $this->booking->forTrustedTenant($tenantId->value);
        $contentById = [];
        foreach ($draft->sections as $content) {
            $contentById[$content->sectionId()->value] = $content;
        }

        $sections = [];
        foreach ($website->sections()->sections() as $metadata) {
            $content = $contentById[$metadata->id->value] ?? null;
            if (! $metadata->enabled()
                || ! $content instanceof WebsiteSectionContentInterface
                || $content->sectionType() !== $metadata->type
                || ! $this->renderable($content, $website, $booking)) {
                continue;
            }
            $sections[] = $this->section($content, $website, $booking, $clinic->contactProfile());
        }

        $branding = $website->branding();
        $contact = $clinic->contactProfile();
        $hours = [];
        foreach ($clinic->weeklyOperatingHours()->all() as $day => $intervals) {
            foreach ($intervals as $interval) {
                $hours[] = new BusinessHourRenderModel(
                    DayOfWeek::from($day)->value,
                    $interval->opensAt->value,
                    $interval->closesAt->value,
                );
            }
        }

        return new PublicWebsiteRenderModel(
            new WebsiteIdentityRenderModel($website->id->value, $website->templateId()->value),
            new BrandingRenderModel(
                $branding->clinicName,
                $branding->tagline,
                $branding->primaryColor,
                $branding->secondaryColor,
                $branding->logoReference?->value,
                $branding->faviconReference?->value,
                $branding->logoDisplaySize->value,
                $branding->whatsAppButtonStyle->value,
            ),
            new SeoRenderModel(
                $website->seo()->metaTitle(),
                $website->seo()->metaDescription(),
                $website->seo()->metaKeywords(),
                null,
                'noindex,nofollow',
                $website->seo()->openGraphTitle(),
                $website->seo()->openGraphDescription(),
                $website->seo()->openGraphImageReference()?->value,
                false,
            ),
            new HeaderRenderModel(
                $branding->clinicName,
                $branding->tagline,
                $branding->logoReference?->value,
                $branding->logoDisplaySize->value,
            ),
            new FooterRenderModel(
                $branding->clinicName,
                $contact->operationalEmail ?? $branding->contactEmail,
                $contact->operationalPhone ?? $branding->contactPhone,
                $contact->postalAddress ?? $branding->address,
                $branding->socialLinks,
                $hours,
                $contact->whatsAppNumber,
                $contact->latitude,
                $contact->longitude,
            ),
            $sections,
            array_values(array_map(
                static fn ($asset): AssetRenderModel => new AssetRenderModel(
                    $asset->id->value,
                    $asset->mimeType->value,
                    $asset->width,
                    $asset->height,
                ),
                array_filter(
                    $website->assets()->assets(),
                    static fn ($asset): bool => $asset->status() === AssetStatus::Available,
                ),
            )),
            new PublicationMetadataRenderModel(
                $website->id->value,
                $draft->version,
                new DateTimeImmutable,
            ),
        );
    }

    private function renderable(
        WebsiteSectionContentInterface $content,
        Website $website,
        PublicBookingFormConfiguration $booking,
    ): bool {
        $activeServices = array_map(static fn ($service): string => $service->id, $booking->services);

        return match (true) {
            $content instanceof HeroSectionContent => $content->isRenderable(),
            $content instanceof AboutSectionContent => $content->isRenderable(),
            $content instanceof ServicesSectionContent => $content->isRenderable($activeServices),
            $content instanceof DoctorsSectionContent => $content->isRenderable(),
            $content instanceof TestimonialsSectionContent => $content->isRenderable(),
            $content instanceof GallerySectionContent => $content->isRenderable(),
            $content instanceof FaqSectionContent => $content->isRenderable(),
            $content instanceof ContactSectionContent => $content->isRenderable(
                $website->branding(),
            ),
            $content instanceof BookingCtaSectionContent => $content->isRenderable(true),
            default => false,
        };
    }

    private function section(
        WebsiteSectionContentInterface $content,
        Website $website,
        PublicBookingFormConfiguration $booking,
        ClinicContactProfile $contact,
    ): SectionRenderContract {
        $branding = $website->branding();
        $services = [];
        if ($content instanceof ServicesSectionContent) {
            $catalogue = [];
            foreach ($booking->services as $service) {
                $catalogue[$service->id] = $service;
            }
            foreach ($content->items as $item) {
                $service = $catalogue[$item->serviceId] ?? null;
                if ($service !== null) {
                    $services[] = new ServiceItemRenderModel(
                        $item->serviceId,
                        $service->name,
                        null,
                        $item->displayOrder,
                        $item->isFeatured,
                    );
                }
            }
        }

        return match (true) {
            $content instanceof HeroSectionContent => new HeroSectionRenderModel(
                $this->required($content->headline),
                $content->subheadline,
                $content->primaryCtaLabel,
                $content->primaryCtaTarget,
                $content->secondaryCtaLabel,
                $content->secondaryCtaTarget,
                $content->heroImageReference?->value,
            ),
            $content instanceof AboutSectionContent => new AboutSectionRenderModel(
                $this->required($content->heading),
                $this->required($content->description),
                $content->imageReference?->value,
            ),
            $content instanceof ServicesSectionContent => new ServicesSectionRenderModel($services),
            $content instanceof DoctorsSectionContent => new DoctorsSectionRenderModel(array_values(
                array_map(
                    static fn ($profile): DoctorRenderModel => new DoctorRenderModel(
                        $profile->name,
                        $profile->professionalTitle,
                        $profile->photo?->value,
                    ),
                    array_filter(
                        $content->profiles,
                        static fn ($profile): bool => $profile->visible,
                    ),
                ),
            )),
            $content instanceof TestimonialsSectionContent => new TestimonialsSectionRenderModel(
                array_values(array_map(
                    static fn ($testimonial): TestimonialRenderModel => new TestimonialRenderModel(
                        $testimonial->quote,
                        $testimonial->authorName,
                    ),
                    array_filter(
                        $content->testimonials,
                        static fn ($testimonial): bool => $testimonial->featured,
                    ),
                )),
            ),
            $content instanceof GallerySectionContent => new GallerySectionRenderModel(array_map(
                static fn ($image, int $index): GalleryImageRenderModel => new GalleryImageRenderModel(
                    $image->imageReference->value,
                    $image->altText,
                    $image->caption,
                    $index + 1,
                    $image->decorative,
                ),
                $content->images,
                array_keys($content->images),
            )),
            $content instanceof FaqSectionContent => new FaqSectionRenderModel(array_map(
                static fn ($entry): FaqEntryRenderModel => new FaqEntryRenderModel(
                    $entry->question,
                    $entry->answer,
                ),
                $content->entries,
            )),
            $content instanceof ContactSectionContent => new ContactSectionRenderModel(
                $contact->operationalEmail ?? $branding->contactEmail,
                $contact->operationalPhone ?? $branding->contactPhone,
                $contact->postalAddress ?? $branding->address,
                $branding->socialLinks,
                [],
                $contact->whatsAppNumber,
                $contact->latitude,
                $contact->longitude,
            ),
            $content instanceof BookingCtaSectionContent => new BookingCtaSectionRenderModel(
                $this->required($content->heading),
                $this->required($content->description),
                $this->required($content->buttonLabel),
            ),
            default => throw new RuntimeException('Draft contains an unsupported Section type.'),
        };
    }

    private function required(?string $value): string
    {
        return $value ?? throw new RuntimeException('Renderable Draft content is incomplete.');
    }
}
