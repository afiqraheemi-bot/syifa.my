<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\WebsiteApproval;

use DateTimeImmutable;

final readonly class DecideWebsiteApprovalCommand
{
    public function __construct(
        public string $tenantId,
        public string $onboardingJobId,
        public string $clinicOwnerIdentityId,
        public string $decision,
        public ?string $reason,
        public int $expectedJobVersion,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
