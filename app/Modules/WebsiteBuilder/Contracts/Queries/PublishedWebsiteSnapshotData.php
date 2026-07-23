<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

use DateTimeImmutable;

final readonly class PublishedWebsiteSnapshotData
{
    /** @param list<PublishedWebsiteSectionSummaryData> $sections */
    public function __construct(
        public string $publicationId,
        public string $websiteId,
        public int $publishedVersion,
        public DateTimeImmutable $publishedAt,
        public string $templateId,
        public string $clinicName,
        public string $metaTitle,
        public string $contentFingerprint,
        public array $sections = [],
    ) {}
}
