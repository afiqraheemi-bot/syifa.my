<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetStatus;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetUsage;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationResult;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use DateTimeImmutable;

final class Website
{
    public function __construct(
        public readonly WebsiteId $id,
        public readonly TenantId $tenantId,
        private TemplateId $templateId,
        private WebsiteBranding $branding,
        private WebsiteLifecycle $lifecycle,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private WebsiteSectionCollection $sections,
        private WebsiteSeoConfiguration $seo,
        private WebsiteAssetCollection $assets,
        private ?PublishedWebsiteSnapshot $publishedSnapshot = null,
        /** @var list<WebsitePublicationHistoryEntry> */
        private array $publicationHistory = [],
        private int $version = 0,
    ) {
        if ($version < 0 || $updatedAt < $createdAt) {
            throw new InvalidWebsiteValueException('Website persisted state is invalid.');
        }
        foreach ($assets->assets() as $asset) {
            if ($asset->tenantId->value !== $tenantId->value) {
                throw new InvalidWebsiteValueException('Website Asset Tenant lineage does not match Website ownership.');
            }
        }
        if (($publishedSnapshot === null) !== ($publicationHistory === [])) {
            throw new InvalidWebsiteValueException('Website publication state is incomplete.');
        }
        if ($publishedSnapshot !== null) {
            $lastHistory = end($publicationHistory);
            if (! $lastHistory instanceof WebsitePublicationHistoryEntry || $publishedSnapshot->websiteId->value !== $id->value || $lastHistory->publicationId->value !== $publishedSnapshot->publicationId->value) {
                throw new InvalidWebsiteValueException('Website publication ownership is invalid.');
            }
        }
    }

    /** @param list<SectionId> $sectionIds */
    public static function create(WebsiteId $id, TenantId $tenantId, TemplateId $templateId, WebsiteBranding $branding, array $sectionIds, DateTimeImmutable $at): self
    {
        return new self($id, $tenantId, $templateId, $branding, WebsiteLifecycle::Draft, $at, $at, WebsiteSectionCollection::defaults($sectionIds, $at), WebsiteSeoConfiguration::defaults($id, $branding, $at), new WebsiteAssetCollection);
    }

    public function templateId(): TemplateId
    {
        return $this->templateId;
    }

    public function branding(): WebsiteBranding
    {
        return $this->branding;
    }

    public function lifecycle(): WebsiteLifecycle
    {
        return $this->lifecycle;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function sections(): WebsiteSectionCollection
    {
        return $this->sections;
    }

    public function seo(): WebsiteSeoConfiguration
    {
        return $this->seo;
    }

    public function assets(): WebsiteAssetCollection
    {
        return $this->assets;
    }

    public function publishedSnapshot(): ?PublishedWebsiteSnapshot
    {
        return $this->publishedSnapshot;
    }

    /** @return list<WebsitePublicationHistoryEntry> */
    public function publicationHistory(): array
    {
        return $this->publicationHistory;
    }

    public function publishedVersion(): int
    {
        return $this->publishedSnapshot === null ? 0 : $this->publishedSnapshot->publishedVersion;
    }

    public function lastPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedSnapshot?->publishedAt;
    }

    public function lastPublishedBy(): ?string
    {
        return $this->publishedSnapshot?->publishedBy;
    }

    public function registerAsset(WebsiteAsset $asset, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->assets->add($asset, $this->tenantId);
        $this->updatedAt = $at;
    }

    public function makeAssetAvailable(AssetId $id, AssetAvailabilityEvidence $evidence, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->assets->asset($id)->markAvailable($evidence, $at);
        $this->updatedAt = $at;
    }

    public function archiveAsset(AssetId $id, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->assets->asset($id)->archive($at);
        $this->updatedAt = $at;
    }

    public function configureSeo(
        string $metaTitle,
        string $metaDescription,
        ?string $metaKeywords,
        ?string $canonicalUrl,
        RobotsDirective $robotsDirective,
        string $openGraphTitle,
        string $openGraphDescription,
        ?AssetId $openGraphImageReference,
        bool $indexingEnabled,
        DateTimeImmutable $at,
    ): void {
        $this->assertNotArchived();
        $this->seo->configure($metaTitle, $metaDescription, $metaKeywords, $canonicalUrl, $robotsDirective, $openGraphTitle, $openGraphDescription, $openGraphImageReference, $indexingEnabled, $at);
        $this->updatedAt = $at;
    }

    public function enableSection(SectionId $id, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->enable($id, $at);
        $this->updatedAt = $at;
    }

    public function disableSection(SectionId $id, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->disable($id, $at);
        $this->updatedAt = $at;
    }

    public function reorderSection(SectionId $id, SectionDisplayOrder $order, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->sections->reorder($id, $order, $at);
        $this->updatedAt = $at;
    }

    public function updateBranding(WebsiteBranding $branding, DateTimeImmutable $at): void
    {
        $this->assertNotArchived();
        $this->branding = $branding;
        $this->updatedAt = $at;
    }

    public function selectTemplate(TemplateId $templateId, DateTimeImmutable $at): void
    {
        if (in_array($this->lifecycle, [WebsiteLifecycle::Published, WebsiteLifecycle::Archived], true)) {
            throw new InvalidWebsiteValueException('Website template cannot change after publication.');
        }
        $this->templateId = $templateId;
        $this->updatedAt = $at;
    }

    public function readyForReview(DateTimeImmutable $at): void
    {
        $this->transition(WebsiteLifecycle::Draft, WebsiteLifecycle::ReadyForReview, $at);
    }

    public function publish(WebsitePublicationEvidence $evidence, WebsitePublicationReadiness $readiness, WebsitePublicationContent $content, PublicationId $publicationId, string $publishedBy, DateTimeImmutable $at): void
    {
        if (! in_array($this->lifecycle, [WebsiteLifecycle::ReadyForReview, WebsiteLifecycle::Published], true)) {
            throw new InvalidWebsiteValueException('Website is not ready to publish.');
        }
        foreach ($this->publicationHistory as $entry) {
            if ($entry->publicationId->value === $publicationId->value) {
                throw new InvalidWebsiteValueException('Publication identity has already been used.');
            }
        }
        $this->assertAssetAvailable($this->branding->logoReference, AssetUsage::Logo);
        $this->assertAssetAvailable($this->branding->faviconReference, AssetUsage::Favicon);
        $this->assertAssetAvailable($this->seo->openGraphImageReference(), AssetUsage::OpenGraphImage);
        foreach ($content->assetReferences() as [$assetId, $usage]) {
            $this->assertAssetAvailable($assetId, $usage);
        }

        $publishedVersion = $this->publishedVersion() + 1;
        $sections = array_map(static fn (WebsiteSection $section): PublishedSectionSnapshot => new PublishedSectionSnapshot($section->id, $section->type, $section->displayOrder()->value, $section->enabled()), $this->sections->sections());
        $sectionContents = $content->capture($this->sections, $publicationId, $this->id, $publishedVersion, $this->branding, $at);
        $assets = [];
        foreach ($this->assets->assets() as $asset) {
            if ($asset->status() === AssetStatus::Available) {
                $assets[] = new PublishedAssetSnapshot($asset->id, $asset->storageKey, $asset->mimeType, $asset->fileSizeBytes, $asset->width, $asset->height, $asset->checksum);
            }
        }
        $branding = $this->branding;
        $seo = $this->seo;
        $snapshot = new PublishedWebsiteSnapshot(
            $publicationId, $this->id, $publishedVersion, $this->version, $at, $publishedBy, $this->templateId,
            $branding->clinicName, $branding->tagline, $branding->primaryColor, $branding->secondaryColor, $branding->logoReference,
            $branding->faviconReference, $branding->contactEmail, $branding->contactPhone, $branding->address, $branding->socialLinks,
            $seo->metaTitle(), $seo->metaDescription(), $seo->metaKeywords(), $seo->canonicalUrl(), $seo->robotsDirective(),
            $seo->openGraphTitle(), $seo->openGraphDescription(), $seo->openGraphImageReference(), $seo->indexingEnabled(),
            $readiness->contentFingerprint, $sections, $assets, $sectionContents,
        );
        $history = new WebsitePublicationHistoryEntry($publicationId, $this->id, $publishedVersion, $at, $publishedBy, PublicationResult::Published);
        $this->publishedSnapshot = $snapshot;
        $this->publicationHistory[] = $history;
        $this->lifecycle = WebsiteLifecycle::Published;
        $this->updatedAt = $at;
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->transition(WebsiteLifecycle::Published, WebsiteLifecycle::Archived, $at);
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version < 1) {
            throw new InvalidWebsiteValueException('Persisted Website version must be positive.');
        }
        $this->version = $version;
    }

    private function transition(WebsiteLifecycle $from, WebsiteLifecycle $to, DateTimeImmutable $at): void
    {
        if ($this->lifecycle !== $from) {
            throw new InvalidWebsiteValueException(sprintf('Website cannot transition from %s to %s.', $this->lifecycle->value, $to->value));
        }
        $this->lifecycle = $to;
        $this->updatedAt = $at;
    }

    private function assertNotArchived(): void
    {
        if ($this->lifecycle === WebsiteLifecycle::Archived) {
            throw new InvalidWebsiteValueException('Archived Website cannot be changed.');
        }
    }

    private function assertAssetAvailable(?AssetId $assetId, AssetUsage $usage): void
    {
        if ($assetId !== null && ! $this->assets->isEligible($assetId, $usage)) {
            throw new InvalidWebsiteValueException('A referenced Website Asset is unavailable for its configured use.');
        }
    }
}
