<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Administration;

use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\Administration\ReassignWebsiteDesignerCommand;
use App\Modules\Onboarding\Contracts\Administration\WebsiteDesignerEligibilityInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;

final readonly class ReassignWebsiteDesignerService
{
    public function __construct(
        private OnboardingJobRepositoryInterface $jobs,
        private WebsiteDesignerEligibilityInterface $designers,
        private OnboardingAuditInterface $audit,
    ) {}

    public function execute(ReassignWebsiteDesignerCommand $command): string
    {
        $job = $this->jobs->findById(new OnboardingJobId($command->onboardingJobId));
        if ($job === null || $job->version() !== $command->expectedVersion) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'Onboarding Job changed since it was loaded.',
            );
        }
        if (! $this->designers->isEligible($command->platformIdentityId)) {
            throw new InvalidWebsiteDesignerAssignmentTransitionException(
                'The selected Website Designer is not eligible for assignment.',
            );
        }

        $newAssignmentId = $this->identifier(
            $command->onboardingJobId,
            $command->platformIdentityId.':'.$command->correlationId,
        );
        $previousVersion = $job->version();
        $job->reassignWebsiteDesigner(
            new WebsiteDesignerAssignmentId($command->currentAssignmentId),
            new WebsiteDesignerAssignmentId($newAssignmentId),
            new PlatformIdentityId($command->platformIdentityId),
            $command->occurredAt,
        );
        $this->jobs->save($job);
        $this->audit->recordDesignerReassignment(
            $this->identifier($newAssignmentId, $command->correlationId),
            $command->actorPlatformIdentityId,
            $job->tenantId->value,
            $job->id->value,
            $command->currentAssignmentId,
            $newAssignmentId,
            $command->platformIdentityId,
            $previousVersion,
            $job->version(),
            $command->correlationId,
            $command->occurredAt,
        );

        return $newAssignmentId;
    }

    private function identifier(string $left, string $right): string
    {
        $hex = substr(hash('sha256', $left.':'.$right), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
