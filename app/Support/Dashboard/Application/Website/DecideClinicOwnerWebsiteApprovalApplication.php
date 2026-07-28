<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Modules\Onboarding\Application\WebsiteApproval\DecideWebsiteApprovalService;
use App\Modules\Onboarding\Contracts\WebsiteApproval\DecideWebsiteApprovalCommand;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReturnWebsiteForCorrectionCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteReview\ReturnWebsiteForCorrectionService;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class DecideClinicOwnerWebsiteApprovalApplication
{
    public function __construct(
        private DecideWebsiteApprovalService $onboarding,
        private ReturnWebsiteForCorrectionService $websites,
        private ConnectionInterface $connection,
    ) {}

    public function execute(
        string $tenantId,
        string $jobId,
        string $clinicOwnerIdentityId,
        string $decision,
        ?string $reason,
        int $expectedJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): OnboardingJob {
        return $this->connection->transaction(function () use (
            $tenantId,
            $jobId,
            $clinicOwnerIdentityId,
            $decision,
            $reason,
            $expectedJobVersion,
            $correlationId,
            $occurredAt,
        ): OnboardingJob {
            $job = $this->onboarding->execute(new DecideWebsiteApprovalCommand(
                $tenantId,
                $jobId,
                $clinicOwnerIdentityId,
                $decision,
                $reason,
                $expectedJobVersion,
                $correlationId,
                $occurredAt,
            ));
            if ($decision === 'request_correction') {
                $approval = $job->websiteApproval();
                if ($approval === null) {
                    throw new \LogicException('Website Approval evidence is unavailable.');
                }
                $this->websites->execute(new ReturnWebsiteForCorrectionCommand(
                    new WebsiteAuthorizationContext(
                        $clinicOwnerIdentityId,
                        'clinic_owner',
                        actorTenantId: $tenantId,
                    ),
                    $tenantId,
                    $job->websiteId->value,
                    $approval->websiteVersion,
                ));
            }

            return $job;
        });
    }
}
