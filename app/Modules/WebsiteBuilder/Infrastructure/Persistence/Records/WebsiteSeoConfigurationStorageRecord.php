<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteSeoConfigurationStorageRecord
{
    public function __construct(public string $websiteId, public string $metaTitle, public string $metaDescription, public ?string $metaKeywords, public ?string $canonicalUrl, public string $robotsDirective, public string $openGraphTitle, public string $openGraphDescription, public ?string $openGraphImageReference, public bool $indexingEnabled, public DateTimeImmutable $domainCreatedAt, public DateTimeImmutable $domainUpdatedAt, public int $version) {}
}
