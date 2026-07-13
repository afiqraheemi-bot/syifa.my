<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Infrastructure\Persistence\Mappers;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobStatus;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\OnboardingJobStorageRecord;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\WebsiteDesignerAssignmentStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OnboardingJobPersistenceMapperTest extends TestCase
{
    public function test_it_reconstitutes_a_planned_job_without_domain_events(): void
    {
        $job = (new OnboardingJobPersistenceMapper)->toDomain($this->jobRecord(), []);

        self::assertSame($this->uuid(1), $job->id->value);
        self::assertSame($this->uuid(2), $job->tenantId->value);
        self::assertSame(OnboardingJobStatus::Planned, $job->status());
        self::assertSame(4, $job->version());
        self::assertSame([], $job->releaseDomainEvents());
    }

    public function test_it_reconstitutes_complete_assignment_history_and_lifecycle_timestamps(): void
    {
        $record = new OnboardingJobStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'in_progress',
            8,
            $this->time('10:00:00'),
            assignedAt: $this->time('10:01:00'),
            inProgressAt: $this->time('10:03:00'),
        );
        $job = (new OnboardingJobPersistenceMapper)->toDomain($record, [
            $this->assignmentRecord(10, 20, 'ended', 'reassigned', '10:02:00'),
            $this->assignmentRecord(11, 21, 'active'),
        ]);

        self::assertSame(OnboardingJobStatus::InProgress, $job->status());
        self::assertEquals($this->time('10:03:00'), $job->lifecycleTimestamps()->inProgressAt);
        self::assertCount(2, $job->websiteDesignerAssignmentHistory());
        self::assertFalse($job->websiteDesignerAssignmentHistory()[0]->isActive());
        self::assertSame($this->uuid(11), $job->activeWebsiteDesignerAssignment()?->id->value);
    }

    public function test_it_rejects_cross_job_or_cross_tenant_assignment_storage(): void
    {
        $foreign = new WebsiteDesignerAssignmentStorageRecord(
            $this->uuid(10),
            $this->uuid(99),
            $this->uuid(98),
            $this->uuid(20),
            'active',
            $this->time('10:01:00'),
            null,
            null,
        );

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        (new OnboardingJobPersistenceMapper)->toDomain($this->jobRecord(), [$foreign]);
    }

    public function test_it_rejects_multiple_active_assignments_during_reconstitution(): void
    {
        $record = new OnboardingJobStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'assigned',
            4,
            $this->time('10:00:00'),
            assignedAt: $this->time('10:01:00'),
        );

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        (new OnboardingJobPersistenceMapper)->toDomain($record, [
            $this->assignmentRecord(10, 20, 'active'),
            $this->assignmentRecord(11, 21, 'active'),
        ]);
    }

    public function test_it_rejects_lifecycle_state_without_its_required_active_assignment(): void
    {
        $record = new OnboardingJobStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'in_progress',
            4,
            $this->time('10:00:00'),
            assignedAt: $this->time('10:01:00'),
            inProgressAt: $this->time('10:02:00'),
        );

        $this->expectException(InvalidWebsiteDesignerAssignmentTransitionException::class);
        (new OnboardingJobPersistenceMapper)->toDomain($record, []);
    }

    private function jobRecord(): OnboardingJobStorageRecord
    {
        return new OnboardingJobStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'planned',
            4,
            $this->time('10:00:00'),
        );
    }

    private function assignmentRecord(
        int $assignmentSuffix,
        int $identitySuffix,
        string $status,
        ?string $reason = null,
        ?string $endedAt = null,
    ): WebsiteDesignerAssignmentStorageRecord {
        return new WebsiteDesignerAssignmentStorageRecord(
            $this->uuid($assignmentSuffix),
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid($identitySuffix),
            $status,
            $this->time('10:01:00'),
            $endedAt === null ? null : $this->time($endedAt),
            $reason,
        );
    }

    private function time(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T'.$time.'+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
