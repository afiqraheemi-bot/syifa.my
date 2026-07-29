<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Domain;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingTaskTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OnboardingTaskTest extends TestCase
{
    public function test_accountable_participant_completes_with_evidence_and_unlocks_dependency(): void
    {
        $now = new DateTimeImmutable('2026-07-29T10:00:00+08:00');
        $job = $this->job($now);
        $owner = $this->task(
            '00000000-0000-4000-8000-000000000011',
            'clinic_inputs',
            OnboardingTaskResponsibility::ClinicOwner,
            OnboardingTaskStatus::AwaitingClinicOwner,
            null,
            $now,
        );
        $designer = $this->task(
            '00000000-0000-4000-8000-000000000012',
            'website_setup',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::NotReady,
            $owner->id,
            $now,
        );
        $job->addTask($owner);
        $job->addTask($designer);

        $job->progressTask(
            $owner->id,
            OnboardingTaskResponsibility::ClinicOwner,
            OnboardingTaskStatus::Completed,
            'clinic_profile_confirmed',
            null,
            $now->modify('+1 hour'),
        );

        self::assertSame(OnboardingTaskStatus::Completed, $job->findTask($owner->id)?->status);
        self::assertSame(OnboardingTaskStatus::Ready, $job->findTask($designer->id)?->status);
    }

    public function test_completion_without_evidence_and_cross_responsibility_are_rejected(): void
    {
        $now = new DateTimeImmutable('2026-07-29T10:00:00+08:00');
        $job = $this->job($now);
        $task = $this->task(
            '00000000-0000-4000-8000-000000000013',
            'clinic_inputs',
            OnboardingTaskResponsibility::ClinicOwner,
            OnboardingTaskStatus::AwaitingClinicOwner,
            null,
            $now,
        );
        $job->addTask($task);

        $this->expectException(InvalidOnboardingTaskTransitionException::class);
        $job->progressTask(
            $task->id,
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::Completed,
            null,
            null,
            $now->modify('+1 hour'),
        );
    }

    public function test_designer_task_requires_active_assignment(): void
    {
        $now = new DateTimeImmutable('2026-07-29T10:00:00+08:00');
        $job = $this->job($now);
        $task = $this->task(
            '00000000-0000-4000-8000-000000000014',
            'website_setup',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::Ready,
            null,
            $now,
        );
        $job->addTask($task);

        $this->expectException(InvalidOnboardingTaskTransitionException::class);
        $job->progressTask(
            $task->id,
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::InProgress,
            null,
            null,
            $now->modify('+1 hour'),
        );
    }

    private function job(DateTimeImmutable $now): OnboardingJob
    {
        return OnboardingJob::create(
            new OnboardingJobId('00000000-0000-4000-8000-000000000001'),
            new TenantId('00000000-0000-4000-8000-000000000002'),
            new WebsiteId('00000000-0000-4000-8000-000000000003'),
            $now,
        );
    }

    private function task(
        string $id,
        string $key,
        OnboardingTaskResponsibility $responsibility,
        OnboardingTaskStatus $status,
        ?OnboardingTaskId $dependency,
        DateTimeImmutable $now,
    ): OnboardingTask {
        return new OnboardingTask(
            new OnboardingTaskId($id),
            new OnboardingJobId('00000000-0000-4000-8000-000000000001'),
            new TenantId('00000000-0000-4000-8000-000000000002'),
            $key,
            ucwords(str_replace('_', ' ', $key)),
            $responsibility,
            $status,
            true,
            true,
            $dependency,
            $now->modify('+14 days'),
            null,
            null,
            null,
            $now,
            $now,
        );
    }
}
