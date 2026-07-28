<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Administration;

use App\Modules\Onboarding\Contracts\Administration\ManageOnboardingJobLifecycleCommand;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;

final readonly class ManageOnboardingJobLifecycleService
{
    public function __construct(
        private OnboardingJobRepositoryInterface $jobs,
        private OnboardingAuditInterface $audit,
        private OnboardingWorkflowTransactionInterface $transaction,
    ) {}

    public function execute(ManageOnboardingJobLifecycleCommand $command): OnboardingJob
    {
        return $this->transaction->run(function () use ($command): OnboardingJob {
            $job = $this->jobs->findById(new OnboardingJobId($command->onboardingJobId));
            if ($job === null || $job->version() !== $command->expectedVersion) {
                throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Onboarding Job changed since it was reviewed.',
                );
            }
            $previousVersion = $job->version();
            $occurredAt = $job->atOrAfterLatestTransition($command->occurredAt);
            match ($command->operation) {
                'complete' => $job->complete($occurredAt),
                'cancel' => $job->cancel($occurredAt),
                'reopen' => $job->reopen($occurredAt),
                default => throw new InvalidOnboardingJobLifecycleTransitionException(
                    'Onboarding Job lifecycle operation is not supported.',
                ),
            };
            $this->jobs->save($job);
            $this->audit->recordJobLifecycleChange(
                $command->actorPlatformIdentityId,
                $job->tenantId->value,
                $job->id->value,
                $command->operation,
                $command->reason,
                $previousVersion,
                $job->version(),
                $command->correlationId,
                $occurredAt,
            );

            return $job;
        });
    }
}
