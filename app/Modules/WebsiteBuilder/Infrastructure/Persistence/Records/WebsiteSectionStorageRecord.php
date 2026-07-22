<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteSectionStorageRecord
{
    public function __construct(public string $id, public string $websiteId, public string $type, public int $displayOrder, public bool $enabled, public DateTimeImmutable $domainCreatedAt, public DateTimeImmutable $domainUpdatedAt, public int $version) {}
}
