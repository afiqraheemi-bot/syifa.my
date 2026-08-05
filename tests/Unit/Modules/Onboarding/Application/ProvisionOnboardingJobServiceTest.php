<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Provisioning\ProvisionOnboardingJobService;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProvisionOnboardingJobServiceTest extends TestCase
{
    public function test_approved_registration_completes_clinic_inputs_and_opens_service_setup(): void
    {
        $stored = null;
        $jobs = $this->createMock(OnboardingJobRepositoryInterface::class);
        $jobs->expects(self::once())->method('find')->willReturn(null);
        $jobs->expects(self::once())->method('save')
            ->willReturnCallback(static function (OnboardingJob $job) use (&$stored): void {
                $stored = $job;
            });

        $service = new ProvisionOnboardingJobService($jobs);
        $registrationReference = '00000000-0000-4000-8000-000000000004';
        $service->execute(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            $registrationReference,
            new DateTimeImmutable('2026-08-01T00:00:00Z'),
        );

        self::assertInstanceOf(OnboardingJob::class, $stored);
        $tasks = [];
        foreach ($stored->tasks() as $task) {
            $tasks[$task->key] = $task;
        }
        self::assertSame('completed', $tasks['clinic_inputs']->status->value);
        self::assertSame(
            'clinic_registration:'.$registrationReference,
            $tasks['clinic_inputs']->evidenceReference,
        );
        self::assertSame('ready', $tasks['service_setup']->status->value);
        self::assertSame('not_ready', $tasks['website_setup']->status->value);
    }
}
