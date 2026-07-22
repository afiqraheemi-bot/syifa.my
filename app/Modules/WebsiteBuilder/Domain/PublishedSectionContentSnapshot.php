<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;

final readonly class PublishedSectionContentSnapshot
{
    /** @param list<PublishedServiceItem> $publishedServices */
    public function __construct(
        public PublicationId $publicationId,
        public WebsiteId $websiteId,
        public SectionId $sectionId,
        public SectionType $sectionType,
        public int $publishedVersion,
        public WebsiteSectionContentInterface $content,
        public string $contentFingerprint,
        public bool $renderable,
        public DateTimeImmutable $createdAt,
        public int $version = 1,
        public ?PublishedContactProjection $contactProjection = null,
        public array $publishedServices = [],
    ) {
        if ($publishedVersion < 1 || $version !== 1 || $content->sectionId()->value !== $sectionId->value || $content->sectionType() !== $sectionType || preg_match('/^[0-9a-f]{64}$/', $contentFingerprint) !== 1) {
            throw new InvalidWebsiteValueException('Published Section Content Snapshot state is invalid.');
        }
        if (($sectionType === SectionType::Contact) !== ($contactProjection !== null)) {
            throw new InvalidWebsiteValueException('Published Contact projection state is invalid.');
        }
        if ($sectionType !== SectionType::Services && $publishedServices !== []) {
            throw new InvalidWebsiteValueException('Published Service projection state is invalid.');
        }
    }
}
