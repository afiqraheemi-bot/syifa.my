<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\WebsiteApproval;

use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\RequestWebsiteApprovalCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteApprovalId;

final readonly class RequestWebsiteApprovalService
{
    public function __construct(
        private OnboardingJobRepositoryInterface $jobs,
        private WebsiteApprovalAuditInterface $audit,
        private OnboardingWorkflowTransactionInterface $transaction,
    ) {}

    public function execute(RequestWebsiteApprovalCommand $command): OnboardingJob
    {
        return $this->transaction->run(function () use ($command): OnboardingJob {
            $job = $this->jobs->findById(new OnboardingJobId($command->onboardingJobId));
            if ($job === null || $job->version() !== $command->expectedJobVersion) {
                throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Onboarding Job changed since Website review was prepared.',
                );
            }
            $occurredAt = $job->atOrAfterLatestTransition($command->occurredAt);
            $job->requestWebsiteApproval(
                new WebsiteApprovalId($command->approvalId),
                new PlatformIdentityId($command->designerPlatformIdentityId),
                $command->websiteVersion,
                $command->draftVersion,
                $occurredAt,
            );
            $this->jobs->save($job);
            $approval = $job->websiteApproval();
            if ($approval === null) {
                throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Website Approval evidence was not created.',
                );
            }
            $this->audit->recordWebsiteApprovalRequested(
                $command->designerPlatformIdentityId,
                $job->tenantId->value,
                $job->id->value,
                $approval->id->value,
                $approval->websiteVersion,
                $approval->draftVersion,
                $job->version(),
                $command->correlationId,
                $occurredAt,
            );

            return $job;
        });
    }
}
