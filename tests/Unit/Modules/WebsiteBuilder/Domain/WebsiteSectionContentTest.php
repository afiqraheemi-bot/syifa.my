<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
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
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteSectionContentTest extends TestCase
{
    #[DataProvider('emptyContentProvider')]
    public function test_default_content_is_typed_identified_and_not_renderable(WebsiteSectionContentInterface $content, SectionType $type): void
    {
        self::assertSame($this->uuid(1), $content->sectionId()->value);
        self::assertSame($type, $content->sectionType());
        self::assertFalse($this->emptyIsRenderable($content));
    }

    /** @return iterable<string, array{WebsiteSectionContentInterface, SectionType}> */
    public static function emptyContentProvider(): iterable
    {
        $id = new SectionId(self::staticUuid(1));
        yield 'hero' => [new HeroSectionContent($id), SectionType::Hero];
        yield 'about' => [new AboutSectionContent($id), SectionType::About];
        yield 'services' => [new ServicesSectionContent($id), SectionType::Services];
        yield 'doctors' => [new DoctorsSectionContent($id), SectionType::Doctors];
        yield 'testimonials' => [new TestimonialsSectionContent($id), SectionType::Testimonials];
        yield 'gallery' => [new GallerySectionContent($id), SectionType::Gallery];
        yield 'faq' => [new FaqSectionContent($id), SectionType::Faq];
        yield 'contact' => [new ContactSectionContent($id), SectionType::Contact];
        yield 'booking CTA' => [new BookingCtaSectionContent($id), SectionType::BookingCta];
    }

    public function test_hero_requires_only_headline_to_be_renderable_and_validates_cta_pairs(): void
    {
        self::assertTrue((new HeroSectionContent($this->sectionId(), 'Care when you need it'))->isRenderable());
        self::assertFalse((new HeroSectionContent($this->sectionId(), null, 'Subheadline'))->isRenderable());

        $this->expectException(InvalidWebsiteValueException::class);
        new HeroSectionContent($this->sectionId(), 'Headline', null, 'Book now');
    }

    public function test_cta_targets_reject_unsafe_or_protocol_relative_urls(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        new HeroSectionContent($this->sectionId(), 'Headline', null, 'Book now', '//untrusted.example');
    }

    public function test_about_requires_heading_and_description_but_image_is_optional(): void
    {
        self::assertTrue((new AboutSectionContent($this->sectionId(), 'About us', 'Trusted care'))->isRenderable());
        self::assertFalse((new AboutSectionContent($this->sectionId(), 'About us'))->isRenderable());
    }

    public function test_services_use_only_opaque_references_and_require_an_active_match(): void
    {
        $content = new ServicesSectionContent($this->sectionId(), [$this->uuid(10), $this->uuid(11)]);
        self::assertFalse($content->isRenderable([$this->uuid(12)]));
        self::assertTrue($content->isRenderable([$this->uuid(11)]));
    }

    public function test_doctors_require_at_least_one_visible_manual_profile(): void
    {
        $hidden = new ManualDoctorProfile($this->uuid(20), 'Dr Hidden', null, false);
        self::assertFalse((new DoctorsSectionContent($this->sectionId(), [$hidden]))->isRenderable());
        self::assertTrue((new DoctorsSectionContent($this->sectionId(), [$hidden, new ManualDoctorProfile($this->uuid(21), 'Dr Visible')]))->isRenderable());
    }

    public function test_testimonials_require_at_least_one_featured_manual_testimonial(): void
    {
        $ordinary = new ManualTestimonial($this->uuid(30), 'Excellent care.', 'Patient', false);
        self::assertFalse((new TestimonialsSectionContent($this->sectionId(), [$ordinary]))->isRenderable());
        self::assertTrue((new TestimonialsSectionContent($this->sectionId(), [$ordinary, new ManualTestimonial($this->uuid(31), 'Recommended.', 'Visitor', true)]))->isRenderable());
    }

    public function test_gallery_and_faq_require_at_least_one_valid_item(): void
    {
        self::assertTrue((new GallerySectionContent($this->sectionId(), [new GalleryImage($this->uuid(40), $this->uuid(41))]))->isRenderable());
        self::assertTrue((new FaqSectionContent($this->sectionId(), [new FaqEntry($this->uuid(50), 'When are you open?', 'Every weekday.')]))->isRenderable());
    }

    public function test_contact_uses_branding_without_duplicating_contact_fields(): void
    {
        $content = new ContactSectionContent($this->sectionId());
        self::assertTrue($content->isRenderable($this->branding()));
        self::assertSame([], get_object_vars($content));
    }

    public function test_booking_cta_requires_complete_content_and_booking_enablement(): void
    {
        $content = new BookingCtaSectionContent($this->sectionId(), 'Book an appointment', 'Choose a suitable time.', 'Book now');
        self::assertFalse($content->isRenderable(false));
        self::assertTrue($content->isRenderable(true));
        self::assertFalse((new BookingCtaSectionContent($this->sectionId(), 'Book', null, 'Book now'))->isRenderable(true));
    }

    public function test_section_requires_enabled_and_matching_renderable_content(): void
    {
        $section = WebsiteSection::create($this->sectionId(), SectionType::Hero, new SectionDisplayOrder(1), new DateTimeImmutable('2026-08-09T00:00:00Z'));
        $content = new HeroSectionContent($this->sectionId(), 'Care when you need it');
        self::assertTrue($section->isRenderEligible($content, $content->isRenderable()));
        $section->setEnabled(false, new DateTimeImmutable('2026-08-09T01:00:00Z'));
        self::assertFalse($section->isRenderEligible($content, $content->isRenderable()));

        $this->expectException(InvalidWebsiteValueException::class);
        $section->isRenderEligible(new AboutSectionContent($this->sectionId(), 'About', 'Description'), true);
    }

    public function test_duplicate_item_identities_and_invalid_references_are_rejected(): void
    {
        $profile = new ManualDoctorProfile($this->uuid(60), 'Dr One');
        try {
            new DoctorsSectionContent($this->sectionId(), [$profile, $profile]);
            self::fail('Expected duplicate identity rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidWebsiteValueException::class);
        new GalleryImage($this->uuid(61), 'not-an-asset-reference');
    }

    private function sectionId(): SectionId
    {
        return new SectionId($this->uuid(1));
    }

    private function branding(): WebsiteBranding
    {
        return new WebsiteBranding('Klinik Syifa', null, '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur');
    }

    private function emptyIsRenderable(WebsiteSectionContentInterface $content): bool
    {
        return match (true) {
            $content instanceof HeroSectionContent => $content->isRenderable(),
            $content instanceof AboutSectionContent => $content->isRenderable(),
            $content instanceof ServicesSectionContent => $content->isRenderable([]),
            $content instanceof DoctorsSectionContent => $content->isRenderable(),
            $content instanceof TestimonialsSectionContent => $content->isRenderable(),
            $content instanceof GallerySectionContent => $content->isRenderable(),
            $content instanceof FaqSectionContent => $content->isRenderable(),
            $content instanceof ContactSectionContent => false,
            $content instanceof BookingCtaSectionContent => $content->isRenderable(false),
            default => throw new InvalidWebsiteValueException('Unknown Website Section content model.'),
        };
    }

    private function uuid(int $suffix): string
    {
        return self::staticUuid($suffix);
    }

    private static function staticUuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
