<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\WebsiteApproval;

use DateTimeImmutable;

final readonly class RequestWebsiteApprovalCommand
{
    public function __construct(
        public string $onboardingJobId,
        public string $approvalId,
        public string $designerPlatformIdentityId,
        public int $websiteVersion,
        public int $draftVersion,
        public int $expectedJobVersion,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
