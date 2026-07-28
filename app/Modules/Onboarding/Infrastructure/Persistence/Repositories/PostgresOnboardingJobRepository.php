<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Repositories;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\StaleOnboardingJobWriteException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Infrastructure\Persistence\Exceptions\InvalidOnboardingJobStorageStateException;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\OnboardingJobStorageRecord;
use App\Modules\Onboarding\Infrastructure\Persistence\Records\WebsiteDesignerAssignmentStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgresOnboardingJobRepository implements OnboardingJobRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly OnboardingJobPersistenceMapper $mapper,
    ) {}

    public function find(TenantId $tenantId, OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        $jobRow = $this->connection
            ->table('onboarding_jobs')
            ->where('tenant_id', $tenantId->value)
            ->where('id', $onboardingJobId->value)
            ->first();

        if ($jobRow === null) {
            return null;
        }

        $assignmentRows = $this->connection
            ->table('website_designer_assignments')
            ->where('tenant_id', $tenantId->value)
            ->where('onboarding_job_id', $onboardingJobId->value)
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();
        $assignmentRecords = [];

        foreach ($assignmentRows as $assignmentRow) {
            $assignmentRecords[] = $this->assignmentRecordFromRow($assignmentRow);
        }

        return $this->mapper->toDomain(
            $this->jobRecordFromRow($jobRow),
            $assignmentRecords,
        );
    }

    public function findById(OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        $tenantId = $this->connection->table('onboarding_jobs')
            ->where('id', $onboardingJobId->value)
            ->value('tenant_id');

        return is_string($tenantId)
            ? $this->find(new TenantId($tenantId), $onboardingJobId)
            : null;
    }

    public function save(OnboardingJob $onboardingJob): void
    {
        $persistedVersion = $this->connection->transaction(
            fn (): int => $onboardingJob->version() === 0
                ? $this->insert($onboardingJob)
                : $this->update($onboardingJob),
        );

        if (! is_int($persistedVersion)) {
            throw new InvalidOnboardingJobStorageStateException(
                'Onboarding Job transaction did not return a persistence version.',
            );
        }

        $onboardingJob->synchronizePersistenceVersion($persistedVersion);
    }

    private function insert(OnboardingJob $job): int
    {
        $record = $this->mapper->jobRecord($job);

        if ($this->connection->table('onboarding_jobs')->where('id', $record->id)->exists()) {
            throw new StaleOnboardingJobWriteException(
                'Onboarding Job already exists; the aggregate version is stale.',
            );
        }

        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $this->connection->table('onboarding_jobs')->insert(
            $this->jobValues($record, 1, $now) + ['created_at' => $now],
        );
        $this->persistAssignmentChanges($job, $now);

        return 1;
    }

    private function update(OnboardingJob $job): int
    {
        $record = $this->mapper->jobRecord($job);
        $newVersion = $record->version + 1;
        $now = $this->databaseTimestamp(new DateTimeImmutable);
        $affected = $this->connection
            ->table('onboarding_jobs')
            ->where('tenant_id', $record->tenantId)
            ->where('id', $record->id)
            ->where('version', $record->version)
            ->update($this->jobValues($record, $newVersion, $now));

        if ($affected !== 1) {
            throw new StaleOnboardingJobWriteException(
                'Onboarding Job write rejected because its aggregate version is stale.',
            );
        }

        $this->persistAssignmentChanges($job, $now);

        return $newVersion;
    }

    /** @return array<string, int|string|null> */
    private function jobValues(OnboardingJobStorageRecord $record, int $version, string $now): array
    {
        return [
            'id' => $record->id,
            'tenant_id' => $record->tenantId,
            'website_id' => $record->websiteId,
            'status' => $record->status,
            'version' => $version,
            'job_created_at' => $this->databaseTimestamp($record->jobCreatedAt),
            'awaiting_inputs_at' => $this->nullableDatabaseTimestamp($record->awaitingInputsAt),
            'assigned_at' => $this->nullableDatabaseTimestamp($record->assignedAt),
            'in_progress_at' => $this->nullableDatabaseTimestamp($record->inProgressAt),
            'blocked_at' => $this->nullableDatabaseTimestamp($record->blockedAt),
            'in_review_at' => $this->nullableDatabaseTimestamp($record->inReviewAt),
            'correction_required_at' => $this->nullableDatabaseTimestamp($record->correctionRequiredAt),
            'ready_for_launch_at' => $this->nullableDatabaseTimestamp($record->readyForLaunchAt),
            'completed_at' => $this->nullableDatabaseTimestamp($record->completedAt),
            'cancelled_at' => $this->nullableDatabaseTimestamp($record->cancelledAt),
            'reopened_at' => $this->nullableDatabaseTimestamp($record->reopenedAt),
            'updated_at' => $now,
        ];
    }

    private function persistAssignmentChanges(OnboardingJob $job, string $now): void
    {
        $storedRows = $this->connection
            ->table('website_designer_assignments')
            ->where('tenant_id', $job->tenantId->value)
            ->where('onboarding_job_id', $job->id->value)
            ->lockForUpdate()
            ->get();
        $stored = [];

        foreach ($storedRows as $storedRow) {
            $record = $this->assignmentRecordFromRow($storedRow);
            $stored[$record->id] = $record;
        }

        $aggregateRecords = [];

        foreach ($this->mapper->assignmentRecords($job) as $record) {
            if ($record->tenantId !== $job->tenantId->value
                || $record->onboardingJobId !== $job->id->value) {
                throw new InvalidOnboardingJobStorageStateException(
                    'Website Designer Assignment has conflicting aggregate ownership.',
                );
            }

            $aggregateRecords[$record->id] = $record;
            $storedRecord = $stored[$record->id] ?? null;

            if ($storedRecord === null) {
                $this->insertAssignment($record, $now);

                continue;
            }

            $this->persistExistingAssignmentTransition($storedRecord, $record, $now);
        }

        foreach ($stored as $storedRecord) {
            if (! isset($aggregateRecords[$storedRecord->id])) {
                throw new InvalidOnboardingJobStorageStateException(
                    'Website Designer Assignment history cannot be removed from its Onboarding Job.',
                );
            }
        }
    }

    private function insertAssignment(WebsiteDesignerAssignmentStorageRecord $record, string $now): void
    {
        $this->connection->table('website_designer_assignments')->insert([
            'id' => $record->id,
            'onboarding_job_id' => $record->onboardingJobId,
            'tenant_id' => $record->tenantId,
            'platform_identity_id' => $record->platformIdentityId,
            'assignment_status' => $record->assignmentStatus,
            'assigned_at' => $this->databaseTimestamp($record->assignedAt),
            'ended_at' => $this->nullableDatabaseTimestamp($record->endedAt),
            'end_reason' => $record->endReason,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function persistExistingAssignmentTransition(
        WebsiteDesignerAssignmentStorageRecord $stored,
        WebsiteDesignerAssignmentStorageRecord $aggregate,
        string $now,
    ): void {
        if ($stored->onboardingJobId !== $aggregate->onboardingJobId
            || $stored->tenantId !== $aggregate->tenantId
            || $stored->platformIdentityId !== $aggregate->platformIdentityId
            || $stored->assignedAt != $aggregate->assignedAt) {
            throw new InvalidOnboardingJobStorageStateException(
                'Immutable Website Designer Assignment history cannot be rewritten.',
            );
        }

        if ($stored->assignmentStatus === $aggregate->assignmentStatus
            && $stored->endedAt == $aggregate->endedAt
            && $stored->endReason === $aggregate->endReason) {
            return;
        }

        if ($stored->assignmentStatus !== 'active'
            || $aggregate->assignmentStatus !== 'ended'
            || $aggregate->endedAt === null
            || $aggregate->endReason === null) {
            throw new InvalidOnboardingJobStorageStateException(
                'Only an active-to-ended Website Designer Assignment transition may update history.',
            );
        }

        $this->connection
            ->table('website_designer_assignments')
            ->where('tenant_id', $aggregate->tenantId)
            ->where('onboarding_job_id', $aggregate->onboardingJobId)
            ->where('id', $aggregate->id)
            ->where('assignment_status', 'active')
            ->update([
                'assignment_status' => 'ended',
                'ended_at' => $this->databaseTimestamp($aggregate->endedAt),
                'end_reason' => $aggregate->endReason,
                'updated_at' => $now,
            ]);
    }

    private function jobRecordFromRow(stdClass $row): OnboardingJobStorageRecord
    {
        return new OnboardingJobStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'tenant_id'),
            $this->stringValue($row, 'website_id'),
            $this->stringValue($row, 'status'),
            $this->integerValue($row, 'version'),
            $this->dateTimeValue($row->job_created_at ?? null, 'job_created_at'),
            $this->nullableDateTimeValue($row->awaiting_inputs_at ?? null, 'awaiting_inputs_at'),
            $this->nullableDateTimeValue($row->assigned_at ?? null, 'assigned_at'),
            $this->nullableDateTimeValue($row->in_progress_at ?? null, 'in_progress_at'),
            $this->nullableDateTimeValue($row->blocked_at ?? null, 'blocked_at'),
            $this->nullableDateTimeValue($row->in_review_at ?? null, 'in_review_at'),
            $this->nullableDateTimeValue($row->correction_required_at ?? null, 'correction_required_at'),
            $this->nullableDateTimeValue($row->ready_for_launch_at ?? null, 'ready_for_launch_at'),
            $this->nullableDateTimeValue($row->completed_at ?? null, 'completed_at'),
            $this->nullableDateTimeValue($row->cancelled_at ?? null, 'cancelled_at'),
            $this->nullableDateTimeValue($row->reopened_at ?? null, 'reopened_at'),
        );
    }

    private function assignmentRecordFromRow(stdClass $row): WebsiteDesignerAssignmentStorageRecord
    {
        $endReason = $row->end_reason ?? null;

        if ($endReason !== null && ! is_string($endReason)) {
            throw new InvalidOnboardingJobStorageStateException('Assignment end_reason must be a string or null.');
        }

        return new WebsiteDesignerAssignmentStorageRecord(
            $this->stringValue($row, 'id'),
            $this->stringValue($row, 'onboarding_job_id'),
            $this->stringValue($row, 'tenant_id'),
            $this->stringValue($row, 'platform_identity_id'),
            $this->stringValue($row, 'assignment_status'),
            $this->dateTimeValue($row->assigned_at ?? null, 'assigned_at'),
            $this->nullableDateTimeValue($row->ended_at ?? null, 'ended_at'),
            $endReason,
        );
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidOnboardingJobStorageStateException(sprintf('Storage field %s must be a string.', $field));
        }

        return $value;
    }

    private function integerValue(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidOnboardingJobStorageStateException(sprintf('Storage field %s must be an integer.', $field));
    }

    private function dateTimeValue(mixed $value, string $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new InvalidOnboardingJobStorageStateException(sprintf('Storage field %s must be a timestamp.', $field));
    }

    private function nullableDateTimeValue(mixed $value, string $field): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTimeValue($value, $field);
    }

    private function databaseTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s.uP');
    }

    private function nullableDatabaseTimestamp(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : $this->databaseTimestamp($dateTime);
    }
}
