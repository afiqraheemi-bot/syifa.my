<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\WebsiteApproval;

use App\Modules\Onboarding\Contracts\WebsiteApproval\DecideWebsiteApprovalCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;

final readonly class DecideWebsiteApprovalService
{
    public function __construct(
        private OnboardingJobRepositoryInterface $jobs,
        private WebsiteApprovalAuditInterface $audit,
        private OnboardingWorkflowTransactionInterface $transaction,
    ) {}

    public function execute(DecideWebsiteApprovalCommand $command): OnboardingJob
    {
        return $this->transaction->run(function () use ($command): OnboardingJob {
            $job = $this->jobs->find(
                new TenantId($command->tenantId),
                new OnboardingJobId($command->onboardingJobId),
            );
            if ($job === null || $job->version() !== $command->expectedJobVersion) {
                throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Onboarding Job changed since Website approval was reviewed.',
                );
            }
            $occurredAt = $job->atOrAfterLatestTransition($command->occurredAt);
            $owner = new ClinicOwnerAuthorityId($command->clinicOwnerIdentityId);
            match ($command->decision) {
                'approve' => $job->approveWebsite($owner, $occurredAt),
                'request_correction' => $job->requestWebsiteCorrection(
                    $owner,
                    trim((string) $command->reason),
                    $occurredAt,
                ),
                default => throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Website Approval decision is not supported.',
                ),
            };
            $this->jobs->save($job);
            $approval = $job->websiteApproval();
            if ($approval === null) {
                throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Website Approval evidence was not found.',
                );
            }
            $this->audit->recordWebsiteApprovalDecision(
                $command->clinicOwnerIdentityId,
                $job->tenantId->value,
                $job->id->value,
                $approval->id->value,
                $command->decision,
                $job->version(),
                $command->correlationId,
                $occurredAt,
            );

            return $job;
        });
    }
}
