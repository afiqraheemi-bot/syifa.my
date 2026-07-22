<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

final readonly class SeoDocumentHead
{
    /** @param array<string, mixed> $structuredData */
    public function __construct(
        public string $title,
        public string $description,
        public string $robots,
        public PublicUrl $canonicalUrl,
        public PublicUrl $openGraphUrl,
        public string $openGraphTitle,
        public string $openGraphDescription,
        public array $structuredData,
    ) {}

    public function jsonLd(): string
    {
        return json_encode($this->structuredData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
