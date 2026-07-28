<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsitePublication;

final readonly class PublishedWebsiteResult
{
    public function __construct(
        public string $websiteId,
        public int $websiteVersion,
        public int $publishedVersion,
        public string $lifecycle,
        public string $publicationId,
        public string $publishedAt,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'website_id' => $this->websiteId,
            'website_version' => $this->websiteVersion,
            'published_version' => $this->publishedVersion,
            'lifecycle' => $this->lifecycle,
            'publication_id' => $this->publicationId,
            'published_at' => $this->publishedAt,
        ];
    }
}
