<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteStorageRecord
{
    /** @param array<string, string> $socialLinks */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $templateId,
        public string $lifecycle,
        public string $clinicName,
        public ?string $tagline,
        public string $primaryColor,
        public string $secondaryColor,
        public ?string $logoReference,
        public ?string $faviconReference,
        public string $contactEmail,
        public string $contactPhone,
        public string $address,
        public array $socialLinks,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
    ) {}
}
