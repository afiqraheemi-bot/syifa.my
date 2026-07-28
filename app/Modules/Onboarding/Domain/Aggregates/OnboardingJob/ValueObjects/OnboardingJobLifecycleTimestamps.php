<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use DateTimeImmutable;

final readonly class OnboardingJobLifecycleTimestamps
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $awaitingInputsAt = null,
        public ?DateTimeImmutable $assignedAt = null,
        public ?DateTimeImmutable $inProgressAt = null,
        public ?DateTimeImmutable $blockedAt = null,
        public ?DateTimeImmutable $inReviewAt = null,
        public ?DateTimeImmutable $correctionRequiredAt = null,
        public ?DateTimeImmutable $readyForLaunchAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $reopenedAt = null,
    ) {}

    public function transitionedTo(OnboardingJobStatus $status, DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return match ($status) {
            OnboardingJobStatus::AwaitingInputs => $this->copy(awaitingInputsAt: $occurredAt),
            OnboardingJobStatus::Assigned => $this->copy(assignedAt: $occurredAt),
            OnboardingJobStatus::InProgress => $this->copy(inProgressAt: $occurredAt),
            OnboardingJobStatus::Blocked => $this->copy(blockedAt: $occurredAt),
            OnboardingJobStatus::InReview => $this->copy(inReviewAt: $occurredAt),
            OnboardingJobStatus::CorrectionRequired => $this->copy(correctionRequiredAt: $occurredAt),
            OnboardingJobStatus::ReadyForLaunch => $this->copy(readyForLaunchAt: $occurredAt),
            OnboardingJobStatus::Completed => $this->copy(completedAt: $occurredAt),
            OnboardingJobStatus::Cancelled => $this->copy(cancelledAt: $occurredAt),
            OnboardingJobStatus::Reopened => $this->copy(reopenedAt: $occurredAt),
            OnboardingJobStatus::Planned => throw new InvalidOnboardingJobLifecycleTransitionException(
                'An Onboarding Job cannot transition back to planned.',
            ),
        };
    }

    public function atOrAfterLatest(DateTimeImmutable $candidate): DateTimeImmutable
    {
        $latest = $this->latest();

        return $candidate < $latest ? $latest->modify('+1 microsecond') : $candidate;
    }

    private function copy(
        ?DateTimeImmutable $awaitingInputsAt = null,
        ?DateTimeImmutable $assignedAt = null,
        ?DateTimeImmutable $inProgressAt = null,
        ?DateTimeImmutable $blockedAt = null,
        ?DateTimeImmutable $inReviewAt = null,
        ?DateTimeImmutable $correctionRequiredAt = null,
        ?DateTimeImmutable $readyForLaunchAt = null,
        ?DateTimeImmutable $completedAt = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?DateTimeImmutable $reopenedAt = null,
    ): self {
        return new self(
            $this->createdAt,
            $awaitingInputsAt ?? $this->awaitingInputsAt,
            $assignedAt ?? $this->assignedAt,
            $inProgressAt ?? $this->inProgressAt,
            $blockedAt ?? $this->blockedAt,
            $inReviewAt ?? $this->inReviewAt,
            $correctionRequiredAt ?? $this->correctionRequiredAt,
            $readyForLaunchAt ?? $this->readyForLaunchAt,
            $completedAt ?? $this->completedAt,
            $cancelledAt ?? $this->cancelledAt,
            $reopenedAt ?? $this->reopenedAt,
        );
    }

    private function assertChronological(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt < $this->latest()) {
            throw new InvalidOnboardingJobLifecycleTransitionException(
                'An Onboarding Job transition cannot occur before its previous transition.',
            );
        }
    }

    private function latest(): DateTimeImmutable
    {
        $latest = $this->createdAt;
        foreach ([
            $this->awaitingInputsAt,
            $this->assignedAt,
            $this->inProgressAt,
            $this->blockedAt,
            $this->inReviewAt,
            $this->correctionRequiredAt,
            $this->readyForLaunchAt,
            $this->completedAt,
            $this->cancelledAt,
            $this->reopenedAt,
        ] as $timestamp) {
            if ($timestamp !== null && $timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }
}
