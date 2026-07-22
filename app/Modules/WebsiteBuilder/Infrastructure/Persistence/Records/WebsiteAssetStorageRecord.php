<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteAssetStorageRecord
{
    public function __construct(public string $id, public string $websiteId, public string $tenantId, public string $storageKey, public string $mimeType, public int $fileSizeBytes, public ?int $width, public ?int $height, public string $checksum, public string $status, public DateTimeImmutable $domainCreatedAt, public DateTimeImmutable $domainUpdatedAt, public int $version) {}
}
