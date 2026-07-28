<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Provisioning;

use App\Modules\Onboarding\Contracts\Provisioning\ProvisionOnboardingJobInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId;
use DateTimeImmutable;

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
        $this->jobs->save($job);

        return $job->id->value;
    }
}
