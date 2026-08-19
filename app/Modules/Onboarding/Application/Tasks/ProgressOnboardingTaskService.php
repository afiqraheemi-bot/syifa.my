<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Tasks;

use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\Tasks\ProgressOnboardingTaskCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingTaskTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;

final readonly class ProgressOnboardingTaskService
{
    public function __construct(
        private OnboardingJobRepositoryInterface $jobs,
        private OnboardingAuditInterface $audit,
        private OnboardingWorkflowTransactionInterface $transaction,
    ) {}

    public function execute(ProgressOnboardingTaskCommand $command): OnboardingJob
    {
        return $this->transaction->run(function () use ($command): OnboardingJob {
            $job = $this->jobs->findById(new OnboardingJobId($command->onboardingJobId));
            if ($job === null || $job->version() !== $command->expectedVersion) {
                throw new InvalidOnboardingTaskTransitionException(
                    'Onboarding Job changed since it was reviewed.',
                );
            }
            $taskId = new OnboardingTaskId($command->taskId);
            $task = $job->findTask($taskId)
                ?? throw new InvalidOnboardingTaskTransitionException(
                    'The Onboarding Task does not belong to this Job.',
                );
            $previousVersion = $job->version();
            $occurredAt = $job->atOrAfterLatestTransition($command->occurredAt);

            if ($command->operation === 'waive') {
                if ($command->actorRole !== 'super_admin') {
                    throw new InvalidOnboardingTaskTransitionException(
                        'Only Super Admin may waive an Onboarding Task.',
                    );
                }
                $job->waiveTask($taskId, (string) $command->waiverReason, $occurredAt);
            } else {
                $responsibility = $this->responsibility($job, $command);
                if ($task->responsibility !== $responsibility) {
                    throw new InvalidOnboardingTaskTransitionException(
                        'The authenticated participant is not accountable for this Onboarding Task.',
                    );
                }
                $job->progressTask(
                    $taskId,
                    $responsibility,
                    $this->status($command->operation),
                    $command->evidenceReference,
                    $command->note,
                    $occurredAt,
                );
            }

            $this->jobs->save($job);
            if ($command->operation === 'waive') {
                $this->audit->recordTaskWaiver(
                    $command->actorId,
                    $job->tenantId->value,
                    $job->id->value,
                    $command->taskId,
                    (string) $command->waiverReason,
                    $previousVersion,
                    $job->version(),
                    $command->correlationId,
                    $command->occurredAt,
                );
            }

            return $job;
        });
    }

    private function responsibility(
        OnboardingJob $job,
        ProgressOnboardingTaskCommand $command,
    ): OnboardingTaskResponsibility {
        if ($command->actorRole === 'website_designer'
            && $job->findActiveAssignmentFor(new PlatformIdentityId($command->actorId)) !== null) {
            return OnboardingTaskResponsibility::WebsiteDesigner;
        }
        if ($command->actorRole === 'clinic_owner'
            && $command->actorTenantId !== null
            && hash_equals($job->tenantId->value, $command->actorTenantId)) {
            return OnboardingTaskResponsibility::ClinicOwner;
        }

        throw new InvalidOnboardingTaskTransitionException(
            'The authenticated participant cannot progress this Onboarding Task.',
        );
    }

    private function status(string $operation): OnboardingTaskStatus
    {
        return match ($operation) {
            'start' => OnboardingTaskStatus::InProgress,
            'block' => OnboardingTaskStatus::Blocked,
            'await_clinic_owner' => OnboardingTaskStatus::AwaitingClinicOwner,
            'await_website_designer' => OnboardingTaskStatus::AwaitingWebsiteDesigner,
            'complete' => OnboardingTaskStatus::Completed,
            'reopen' => OnboardingTaskStatus::Reopened,
            default => throw new InvalidOnboardingTaskTransitionException(
                'Onboarding Task operation is not supported.',
            ),
        };
    }
}
