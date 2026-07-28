<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class WebsiteApprovalStorageRecord
{
    public function __construct(
        public string $id,
        public string $onboardingJobId,
        public string $tenantId,
        public string $websiteId,
        public string $status,
        public int $websiteVersion,
        public int $draftVersion,
        public string $requestedBy,
        public DateTimeImmutable $requestedAt,
        public ?string $decidedBy = null,
        public ?string $correctionNote = null,
        public ?DateTimeImmutable $decidedAt = null,
    ) {}
}
