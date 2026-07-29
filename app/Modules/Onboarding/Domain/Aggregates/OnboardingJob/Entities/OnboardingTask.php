<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingTaskTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class OnboardingTask
{
    public function __construct(
        public OnboardingTaskId $id,
        public OnboardingJobId $onboardingJobId,
        public TenantId $tenantId,
        public string $key,
        public string $title,
        public OnboardingTaskResponsibility $responsibility,
        public OnboardingTaskStatus $status,
        public bool $mandatory,
        public bool $blocking,
        public ?OnboardingTaskId $dependsOnTaskId,
        public ?DateTimeImmutable $dueAt,
        public ?string $evidenceReference,
        public ?string $note,
        public ?string $waiverReason,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $completedAt = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1) {
            throw new InvalidOnboardingTaskTransitionException('An Onboarding Task key is invalid.');
        }
        if (trim($title) === '' || mb_strlen($title) > 160) {
            throw new InvalidOnboardingTaskTransitionException('An Onboarding Task title is required and cannot exceed 160 characters.');
        }
        if ($dependsOnTaskId?->value === $id->value) {
            throw new InvalidOnboardingTaskTransitionException('An Onboarding Task cannot depend on itself.');
        }
        if ($dueAt !== null && $dueAt < $createdAt) {
            throw new InvalidOnboardingTaskTransitionException('An Onboarding Task due time cannot precede its creation.');
        }
        if ($updatedAt < $createdAt || ($completedAt !== null && $completedAt < $createdAt)) {
            throw new InvalidOnboardingTaskTransitionException('Onboarding Task timestamps are inconsistent.');
        }
        if ($status === OnboardingTaskStatus::Completed && ($completedAt === null || trim((string) $evidenceReference) === '')) {
            throw new InvalidOnboardingTaskTransitionException('A completed Onboarding Task requires completion evidence.');
        }
        if ($status === OnboardingTaskStatus::Waived && trim((string) $waiverReason) === '') {
            throw new InvalidOnboardingTaskTransitionException('A waived Onboarding Task requires a reason.');
        }
        if (! in_array($status, [OnboardingTaskStatus::Completed, OnboardingTaskStatus::Waived], true) && $completedAt !== null) {
            throw new InvalidOnboardingTaskTransitionException('Only completed or waived Tasks may have a completion time.');
        }
    }

    public function transition(
        OnboardingTaskStatus $status,
        ?string $evidenceReference,
        ?string $note,
        ?string $waiverReason,
        DateTimeImmutable $occurredAt,
    ): self {
        if (in_array($this->status, [OnboardingTaskStatus::Cancelled], true)) {
            throw new InvalidOnboardingTaskTransitionException('A cancelled Onboarding Task cannot be progressed.');
        }
        if ($occurredAt < $this->updatedAt) {
            throw new InvalidOnboardingTaskTransitionException('An Onboarding Task transition cannot move backwards in time.');
        }

        $allowed = match ($status) {
            OnboardingTaskStatus::InProgress, OnboardingTaskStatus::Blocked,
            OnboardingTaskStatus::AwaitingClinicOwner, OnboardingTaskStatus::AwaitingWebsiteDesigner,
            OnboardingTaskStatus::Completed, OnboardingTaskStatus::Waived,
            OnboardingTaskStatus::Reopened, OnboardingTaskStatus::Cancelled,
            OnboardingTaskStatus::Ready => true,
            default => false,
        };
        if (! $allowed) {
            throw new InvalidOnboardingTaskTransitionException('The requested Onboarding Task transition is not executable.');
        }

        return new self(
            $this->id,
            $this->onboardingJobId,
            $this->tenantId,
            $this->key,
            $this->title,
            $this->responsibility,
            $status,
            $this->mandatory,
            $this->blocking,
            $this->dependsOnTaskId,
            $this->dueAt,
            $this->normalize($evidenceReference),
            $this->normalize($note),
            $status === OnboardingTaskStatus::Waived ? $this->normalize($waiverReason) : null,
            $this->createdAt,
            $occurredAt,
            in_array($status, [OnboardingTaskStatus::Completed, OnboardingTaskStatus::Waived], true) ? $occurredAt : null,
        );
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
