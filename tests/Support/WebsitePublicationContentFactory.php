<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\WebsiteBuilder\Domain\PublishedBusinessHour;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqEntry;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GalleryImage;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualDoctorProfile;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualTestimonial;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicePresentationItem;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\ServicePublicationProjection;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;
use DateTimeImmutable;

final class WebsitePublicationContentFactory
{
    public static function complete(
        Website $website,
        string $serviceName = 'Rawatan Kesihatan Am',
        ?PublishedContactProjection $contact = null,
        ?string $galleryAltText = 'Ruang menunggu klinik yang selesa',
        ?string $galleryCaption = 'Ruang menunggu utama',
        bool $serviceFeatured = false,
    ): WebsitePublicationContent {
        $at = new DateTimeImmutable('2026-08-07T00:00:00Z');
        $serviceId = self::uuid(702);
        $website->configureServicesPresentation([new ServicePresentationItem($serviceId, 1, $serviceFeatured)], [$serviceId], $at);
        $galleryAssetId = new AssetId(self::uuid(9990));
        $exists = array_any($website->assets()->assets(), static fn (WebsiteAsset $asset): bool => $asset->id->value === $galleryAssetId->value);
        if (! $exists) {
            $asset = WebsiteAsset::register($galleryAssetId, $website->tenantId, 'published-content/gallery.png', AssetMimeType::Png, 2048, 800, 600, str_repeat('e', 64), $at);
            $website->registerAsset($asset, $at);
            $website->makeAssetAvailable($galleryAssetId, new AssetAvailabilityEvidence(true, true), $at);
        }
        $contents = [];
        $renderability = [];
        foreach ($website->sections()->sections() as $index => $section) {
            $contents[] = match ($section->type) {
                SectionType::Hero => new HeroSectionContent($section->id, 'Trusted healthcare'),
                SectionType::About => new AboutSectionContent($section->id, 'About us', 'Care for every family.'),
                SectionType::Services => $website->servicesPresentation(),
                SectionType::Doctors => new DoctorsSectionContent($section->id, [new ManualDoctorProfile(self::uuid(710 + $index), 'Dr Syifa')]),
                SectionType::Testimonials => new TestimonialsSectionContent($section->id, [new ManualTestimonial(self::uuid(720 + $index), 'Excellent care.', 'Patient', true)]),
                SectionType::Gallery => new GallerySectionContent($section->id, [new GalleryImage(self::uuid(740 + $index), $galleryAssetId, $galleryAltText, $galleryCaption)]),
                SectionType::Faq => new FaqSectionContent($section->id, [new FaqEntry(self::uuid(730 + $index), 'When are you open?', 'Every weekday.')]),
                SectionType::Contact => new ContactSectionContent($section->id),
                SectionType::BookingCta => new BookingCtaSectionContent($section->id, 'Book an appointment', 'Choose a convenient time.', 'Book now'),
            };
            $renderability[$section->id->value] = true;
        }

        return new WebsitePublicationContent(
            $contents,
            $renderability,
            [new ServicePublicationProjection($serviceId, $serviceName, 'Penilaian kesihatan untuk seisi keluarga.')],
            $contact ?? new PublishedContactProjection(
                'hello@clinic.test',
                '+6012',
                'Kuala Lumpur',
                ['instagram' => 'https://instagram.com/clinic'],
                [new PublishedBusinessHour(1, '09:00', '17:00')],
                '+60123456789',
                3.139,
                101.6869,
            ),
        );
    }

    private static function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
