<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Mappers;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\WebsiteDesignerAssignment;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobLifecycleTimestamps;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentEndReason;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\OnboardingJobStorageRecord;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\WebsiteDesignerAssignmentStorageRecord;

final class OnboardingJobPersistenceMapper
{
    /** @param list<WebsiteDesignerAssignmentStorageRecord> $assignmentRecords */
    public function toDomain(
        OnboardingJobStorageRecord $jobRecord,
        array $assignmentRecords,
    ): OnboardingJob {
        $assignments = [];

        foreach ($assignmentRecords as $record) {
            $assignments[] = new WebsiteDesignerAssignment(
                new WebsiteDesignerAssignmentId($record->id),
                new OnboardingJobId($record->onboardingJobId),
                new PlatformIdentityId($record->platformIdentityId),
                new TenantId($record->tenantId),
                WebsiteDesignerAssignmentStatus::from($record->assignmentStatus),
                $record->assignedAt,
                $record->endedAt,
                $record->endReason === null
                    ? null
                    : WebsiteDesignerAssignmentEndReason::from($record->endReason),
            );
        }

        return OnboardingJob::reconstitute(
            new OnboardingJobId($jobRecord->id),
            new TenantId($jobRecord->tenantId),
            new WebsiteId($jobRecord->websiteId),
            OnboardingJobStatus::from($jobRecord->status),
            new OnboardingJobLifecycleTimestamps(
                $jobRecord->jobCreatedAt,
                $jobRecord->awaitingInputsAt,
                $jobRecord->assignedAt,
                $jobRecord->inProgressAt,
                $jobRecord->blockedAt,
                $jobRecord->inReviewAt,
                $jobRecord->correctionRequiredAt,
                $jobRecord->readyForLaunchAt,
                $jobRecord->completedAt,
                $jobRecord->cancelledAt,
                $jobRecord->reopenedAt,
            ),
            $assignments,
            $jobRecord->version,
        );
    }

    public function jobRecord(OnboardingJob $job): OnboardingJobStorageRecord
    {
        $timestamps = $job->lifecycleTimestamps();

        return new OnboardingJobStorageRecord(
            $job->id->value,
            $job->tenantId->value,
            $job->websiteId->value,
            $job->status()->value,
            $job->version(),
            $timestamps->createdAt,
            $timestamps->awaitingInputsAt,
            $timestamps->assignedAt,
            $timestamps->inProgressAt,
            $timestamps->blockedAt,
            $timestamps->inReviewAt,
            $timestamps->correctionRequiredAt,
            $timestamps->readyForLaunchAt,
            $timestamps->completedAt,
            $timestamps->cancelledAt,
            $timestamps->reopenedAt,
        );
    }

    /** @return list<WebsiteDesignerAssignmentStorageRecord> */
    public function assignmentRecords(OnboardingJob $job): array
    {
        $records = [];

        foreach ($job->websiteDesignerAssignmentHistory() as $assignment) {
            $records[] = new WebsiteDesignerAssignmentStorageRecord(
                $assignment->id->value,
                $assignment->onboardingJobId->value,
                $assignment->tenantId->value,
                $assignment->platformIdentityId->value,
                $assignment->status->value,
                $assignment->assignedAt,
                $assignment->endedAt,
                $assignment->endReason?->value,
            );
        }

        return $records;
    }
}
