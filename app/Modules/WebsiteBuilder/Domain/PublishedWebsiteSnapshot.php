<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;

final readonly class PublishedWebsiteSnapshot
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  list<PublishedSectionSnapshot>  $sections
     * @param  list<PublishedAssetSnapshot>  $assets
     * @param  list<PublishedSectionContentSnapshot>  $sectionContents
     */
    public function __construct(
        public PublicationId $publicationId,
        public WebsiteId $websiteId,
        public int $publishedVersion,
        public int $sourceWebsiteVersion,
        public DateTimeImmutable $publishedAt,
        public string $publishedBy,
        public TemplateId $templateId,
        public string $clinicName,
        public ?string $tagline,
        public string $primaryColor,
        public string $secondaryColor,
        public ?AssetId $logoAssetId,
        public ?AssetId $faviconAssetId,
        public string $contactEmail,
        public string $contactPhone,
        public string $address,
        public array $socialLinks,
        public string $metaTitle,
        public string $metaDescription,
        public ?string $metaKeywords,
        public ?string $canonicalUrl,
        public RobotsDirective $robotsDirective,
        public string $openGraphTitle,
        public string $openGraphDescription,
        public ?AssetId $openGraphImageAssetId,
        public bool $indexingEnabled,
        public string $contentFingerprint,
        public array $sections,
        public array $assets,
        public array $sectionContents,
    ) {
        if ($publishedVersion < 1 || $sourceWebsiteVersion < 0 || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $publishedBy) !== 1 || preg_match('/^[0-9a-f]{64}$/', $contentFingerprint) !== 1) {
            throw new InvalidWebsiteValueException('Published Website Snapshot state is invalid.');
        }
        $sectionIds = array_map(static fn (PublishedSectionSnapshot $section): string => $section->sectionId->value, $sections);
        $orders = array_map(static fn (PublishedSectionSnapshot $section): int => $section->displayOrder, $sections);
        $assetIds = array_map(static fn (PublishedAssetSnapshot $asset): string => $asset->assetId->value, $assets);
        $contentSectionIds = array_map(static fn (PublishedSectionContentSnapshot $content): string => $content->sectionId->value, $sectionContents);
        if (count(array_unique($sectionIds)) !== count($sectionIds) || count(array_unique($orders)) !== count($orders) || count(array_unique($assetIds)) !== count($assetIds) || count($sectionContents) !== count($sections) || $contentSectionIds !== $sectionIds) {
            throw new InvalidWebsiteValueException('Published Website Snapshot children are invalid.');
        }
    }
}
