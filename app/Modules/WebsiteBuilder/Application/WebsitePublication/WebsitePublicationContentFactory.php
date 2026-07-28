<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePublication;

use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\PublishedBusinessHour;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
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
use App\Modules\WebsiteBuilder\Domain\ServicePublicationProjection;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;

final readonly class WebsitePublicationContentFactory
{
    public function create(
        Website $website,
        WebsiteDraftContent $draft,
        Clinic $clinic,
        PublicBookingFormConfiguration $booking,
    ): WebsitePublicationContent {
        $activeServices = [];
        $serviceProjections = [];
        foreach ($booking->services as $service) {
            $activeServices[] = $service->id;
            $serviceProjections[] = new ServicePublicationProjection(
                $service->id,
                $service->name,
                null,
            );
        }

        $renderability = [];
        foreach ($draft->sections as $content) {
            $renderability[$content->sectionId()->value] = $this->renderable(
                $content,
                $website,
                $activeServices,
            );
        }

        $contact = $clinic->contactProfile();
        $branding = $website->branding();
        $hours = [];
        foreach ($clinic->weeklyOperatingHours()->all() as $day => $intervals) {
            foreach ($intervals as $interval) {
                $hours[] = new PublishedBusinessHour(
                    (int) $day,
                    $interval->opensAt->value,
                    $interval->closesAt->value,
                );
            }
        }

        return new WebsitePublicationContent(
            $draft->sections,
            $renderability,
            $serviceProjections,
            new PublishedContactProjection(
                $contact->operationalEmail ?? $branding->contactEmail,
                $contact->operationalPhone ?? $branding->contactPhone,
                $contact->postalAddress ?? $branding->address,
                $branding->socialLinks,
                $hours,
                $contact->whatsAppNumber,
                $contact->latitude,
                $contact->longitude,
            ),
        );
    }

    /** @param list<string> $activeServices */
    private function renderable(
        WebsiteSectionContentInterface $content,
        Website $website,
        array $activeServices,
    ): bool {
        return match (true) {
            $content instanceof HeroSectionContent => $content->isRenderable(),
            $content instanceof AboutSectionContent => $content->isRenderable(),
            $content instanceof ServicesSectionContent => $content->isRenderable($activeServices),
            $content instanceof DoctorsSectionContent => $content->isRenderable(),
            $content instanceof TestimonialsSectionContent => $content->isRenderable(),
            $content instanceof GallerySectionContent => $content->isRenderable(),
            $content instanceof FaqSectionContent => $content->isRenderable(),
            $content instanceof ContactSectionContent => $content->isRenderable($website->branding()),
            $content instanceof BookingCtaSectionContent => $content->isRenderable(true),
            default => false,
        };
    }
}
