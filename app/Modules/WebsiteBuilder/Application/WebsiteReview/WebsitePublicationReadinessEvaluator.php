<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteReview;

use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;

final readonly class WebsitePublicationReadinessEvaluator
{
    public function __construct(private WebsiteDraftSectionCodec $codec) {}

    /** @param list<string> $activeServiceReferences */
    public function evaluate(
        Website $website,
        WebsiteDraftContent $draft,
        array $activeServiceReferences,
    ): WebsitePublicationReadiness {
        $ownershipValid = $website->id->value === $draft->websiteId->value
            && $website->tenantId->value === $draft->tenantId->value;
        $sectionContentValid = true;
        $enabledSectionsRenderable = true;
        $sectionAssetsAvailable = true;
        $blockers = [];

        if (! $ownershipValid) {
            $blockers[] = 'The saved draft does not belong to this Website.';
        }

        $contentsById = [];
        foreach ($draft->sections as $content) {
            $contentsById[$content->sectionId()->value] = $content;
            if (! $this->assetsAvailable($website, $content)) {
                $sectionAssetsAvailable = false;
                $blockers[] = $this->sectionLabel($content->sectionType()).' contains an image that is missing, unavailable or not approved for this Website.';
            }
        }

        foreach ($website->sections()->sections() as $section) {
            $content = $contentsById[$section->id->value] ?? null;
            if (! $content instanceof WebsiteSectionContentInterface
                || $content->sectionType() !== $section->type) {
                $sectionContentValid = false;
                $enabledSectionsRenderable = false;
                $blockers[] = $this->sectionLabel($section->type).' draft content is missing or does not match the Website section.';

                continue;
            }
            if ($section->enabled() && ! $this->renderable(
                $content,
                $website,
                $activeServiceReferences,
            )) {
                $enabledSectionsRenderable = false;
                $blockers[] = $this->incompleteSectionReason($content);
            }
            if ($section->enabled() && $content instanceof GallerySectionContent) {
                foreach ($content->images as $image) {
                    if (! $image->decorative && $image->altText === null) {
                        $blockers[] = 'Each informative Gallery image needs alternative text. Add it, or mark the image as decorative.';

                        break;
                    }
                }
            }
        }

        if ($blockers !== []) {
            throw new InvalidWebsiteValueException(
                "Complete these items before submitting for review:\n".implode(
                    "\n",
                    array_map(static fn (string $blocker): string => '• '.$blocker, array_values(array_unique($blockers))),
                ),
            );
        }

        $encoded = array_map($this->codec->encode(...), $draft->sections);
        $fingerprint = hash('sha256', json_encode([
            'website_id' => $website->id->value,
            'website_version' => $website->version(),
            'draft_version' => $draft->version,
            'sections' => $encoded,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return new WebsitePublicationReadiness(
            websiteConfigurationValid: true,
            sectionContentValid: $sectionContentValid,
            enabledSectionsRenderable: $enabledSectionsRenderable,
            sectionAssetsAvailable: $sectionAssetsAvailable,
            seoValid: true,
            ownershipValid: $ownershipValid,
            contentFingerprint: $fingerprint,
        );
    }

    private function incompleteSectionReason(WebsiteSectionContentInterface $content): string
    {
        return match (true) {
            $content instanceof HeroSectionContent => 'Homepage needs a headline before it can be reviewed.',
            $content instanceof AboutSectionContent => 'About needs a heading and description before it can be reviewed.',
            $content instanceof ServicesSectionContent => 'Services must include at least one active clinic Service.',
            $content instanceof DoctorsSectionContent => 'Doctors needs at least one visible doctor profile.',
            $content instanceof TestimonialsSectionContent => 'Testimonials needs at least one testimonial marked to display on the Website.',
            $content instanceof GallerySectionContent => 'Gallery needs at least one image.',
            $content instanceof FaqSectionContent => 'FAQ needs at least one complete question and answer.',
            $content instanceof ContactSectionContent => 'Contact needs a clinic phone number or email address.',
            $content instanceof BookingCtaSectionContent => 'Booking call to action needs a heading, description and button label.',
            default => $this->sectionLabel($content->sectionType()).' is incomplete.',
        };
    }

    private function sectionLabel(SectionType $type): string
    {
        return match ($type) {
            SectionType::Hero => 'Homepage',
            SectionType::About => 'About',
            SectionType::Services => 'Services',
            SectionType::Doctors => 'Doctors',
            SectionType::Testimonials => 'Testimonials',
            SectionType::Gallery => 'Gallery',
            SectionType::Faq => 'FAQ',
            SectionType::Contact => 'Contact',
            SectionType::BookingCta => 'Booking call to action',
        };
    }

    /** @param list<string> $activeServiceReferences */
    private function renderable(
        WebsiteSectionContentInterface $content,
        Website $website,
        array $activeServiceReferences,
    ): bool {
        return match (true) {
            $content instanceof HeroSectionContent => $content->isRenderable(),
            $content instanceof AboutSectionContent => $content->isRenderable(),
            $content instanceof ServicesSectionContent => $content->isRenderable(
                $activeServiceReferences,
            ),
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

    private function assetsAvailable(
        Website $website,
        WebsiteSectionContentInterface $content,
    ): bool {
        $references = [];
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

        foreach ($references as [$assetId, $usage]) {
            if (! $website->assets()->isEligible($assetId, $usage)) {
                return false;
            }
        }

        return true;
    }
}
