<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteReview;

use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
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

        $contentsById = [];
        foreach ($draft->sections as $content) {
            $contentsById[$content->sectionId()->value] = $content;
            if (! $this->assetsAvailable($website, $content)) {
                $sectionAssetsAvailable = false;
            }
        }

        foreach ($website->sections()->sections() as $section) {
            $content = $contentsById[$section->id->value] ?? null;
            if (! $content instanceof WebsiteSectionContentInterface
                || $content->sectionType() !== $section->type) {
                $sectionContentValid = false;
                $enabledSectionsRenderable = false;

                continue;
            }
            if ($section->enabled() && ! $this->renderable(
                $content,
                $website,
                $activeServiceReferences,
            )) {
                $enabledSectionsRenderable = false;
            }
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
