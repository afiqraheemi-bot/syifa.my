<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Provisioning;

use App\Modules\Onboarding\Contracts\Provisioning\ProvisionOnboardingJobInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class ProvisionOnboardingJobService implements ProvisionOnboardingJobInterface
{
    public function __construct(private OnboardingJobRepositoryInterface $jobs) {}

    public function execute(
        string $onboardingJobId,
        string $tenantId,
        string $websiteId,
        DateTimeImmutable $occurredAt,
    ): string {
        $tenant = new TenantId($tenantId);
        $jobId = new OnboardingJobId($onboardingJobId);
        $existing = $this->jobs->find($tenant, $jobId);
        if ($existing !== null) {
            if ($existing->websiteId->value !== $websiteId) {
                throw new \RuntimeException('Provisioned Onboarding Job lineage conflicts with the Website.');
            }

            return $existing->id->value;
        }

        $job = OnboardingJob::create(
            $jobId,
            $tenant,
            new WebsiteId($websiteId),
            $occurredAt,
        );
        $this->addStandardTasks($job, $occurredAt);
        $this->jobs->save($job);

        return $job->id->value;
    }

    private function addStandardTasks(OnboardingJob $job, DateTimeImmutable $occurredAt): void
    {
        $previous = null;
        foreach ([
            ['clinic_inputs', 'Provide clinic information and content', OnboardingTaskResponsibility::ClinicOwner],
            ['service_setup', 'Configure active clinic services', OnboardingTaskResponsibility::WebsiteDesigner],
            ['website_setup', 'Prepare Website content, template, and SEO', OnboardingTaskResponsibility::WebsiteDesigner],
            ['booking_setup', 'Configure the public booking form', OnboardingTaskResponsibility::WebsiteDesigner],
            ['website_approval', 'Review and approve the prepared Website', OnboardingTaskResponsibility::ClinicOwner],
            ['launch_readiness', 'Confirm launch readiness evidence', OnboardingTaskResponsibility::WebsiteDesigner],
        ] as $order => [$key, $title, $responsibility]) {
            $id = new OnboardingTaskId((string) Str::uuid());
            $job->addTask(new OnboardingTask(
                $id,
                $job->id,
                $job->tenantId,
                $key,
                $title,
                $responsibility,
                $order === 0 ? OnboardingTaskStatus::AwaitingClinicOwner : OnboardingTaskStatus::NotReady,
                true,
                true,
                $previous,
                $occurredAt->modify('+14 days'),
                null,
                null,
                null,
                $occurredAt,
                $occurredAt,
            ));
            $previous = $id;
        }
    }
}
