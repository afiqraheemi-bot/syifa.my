<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Provisioning;

use DateTimeImmutable;

final readonly class ProvisionWebsiteFoundationCommand
{
    /** @param list<string> $sectionIds */
    public function __construct(
        public string $tenantId,
        public string $clinicId,
        public string $websiteId,
        public array $sectionIds,
        public string $clinicName,
        public string $clinicEmail,
        public string $clinicPhone,
        public string $clinicAddress,
        public DateTimeImmutable $occurredAt,
    ) {}
}
