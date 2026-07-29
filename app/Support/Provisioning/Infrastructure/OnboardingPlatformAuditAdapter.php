<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;

final readonly class OnboardingPlatformAuditAdapter implements OnboardingAuditInterface, WebsiteApprovalAuditInterface
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    public function recordDesignerAssignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $assignmentId,
        string $designerId,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $auditEntryId,
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            $tenantId,
            'onboarding.website_designer.assign',
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'website_designer_assignment',
                'target_label' => sprintf(
                    'assignment=%s;designer=%s;version=%d',
                    $assignmentId,
                    $designerId,
                    $resultingVersion,
                ),
            ],
        ));
    }

    public function recordDesignerReassignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $previousAssignmentId,
        string $newAssignmentId,
        string $designerId,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $auditEntryId,
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            $tenantId,
            'onboarding.website_designer.reassign',
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'website_designer_assignment',
                'target_label' => sprintf(
                    'previous_assignment=%s;new_assignment=%s;designer=%s;version=%d->%d',
                    $previousAssignmentId,
                    $newAssignmentId,
                    $designerId,
                    $previousVersion,
                    $resultingVersion,
                ),
            ],
        ));
    }

    public function recordJobLifecycleChange(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $operation,
        ?string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $this->identifier($jobId, $correlationId.':'.$operation),
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            $tenantId,
            'onboarding.job.'.$operation,
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'onboarding_job',
                'target_label' => sprintf(
                    'operation=%s;reason=%s;version=%d->%d',
                    $operation,
                    $reason === null ? 'not_required' : $reason,
                    $previousVersion,
                    $resultingVersion,
                ),
            ],
        ));
    }

    public function recordTaskWaiver(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $taskId,
        string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->audit->record(new AuditEntryData(
            $this->identifier($taskId, $correlationId.':waive'),
            $occurredAt,
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            $tenantId,
            'onboarding.task.waive',
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'onboarding_task',
                'target_label' => sprintf(
                    'task=%s;reason=%s;version=%d->%d',
                    $taskId,
                    $reason,
                    $previousVersion,
                    $resultingVersion,
                ),
            ],
        ));
    }

    public function recordWebsiteApprovalRequested(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        int $websiteVersion,
        int $draftVersion,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->recordApproval(
            $this->identifier($approvalId, $correlationId.':requested'),
            $actorId,
            $tenantId,
            $jobId,
            'onboarding.website_approval.request',
            $approvalId,
            sprintf('website_version=%d;draft_version=%d;job_version=%d', $websiteVersion, $draftVersion, $resultingJobVersion),
            $correlationId,
            $occurredAt,
            AuditActorType::PlatformIdentity,
        );
    }

    public function recordWebsiteApprovalDecision(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        string $decision,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->recordApproval(
            $this->identifier($approvalId, $correlationId.':'.$decision),
            $actorId,
            $tenantId,
            $jobId,
            'onboarding.website_approval.'.$decision,
            $approvalId,
            sprintf('decision=%s;job_version=%d', $decision, $resultingJobVersion),
            $correlationId,
            $occurredAt,
            AuditActorType::ClinicOwner,
        );
    }

    private function recordApproval(
        string $auditEntryId,
        string $actorId,
        string $tenantId,
        string $jobId,
        string $action,
        string $approvalId,
        string $label,
        string $correlationId,
        DateTimeImmutable $occurredAt,
        AuditActorType $actorType,
    ): void {
        $this->audit->record(new AuditEntryData(
            $auditEntryId,
            $occurredAt,
            new AuditActorData($actorType->value, $actorId),
            $tenantId,
            $action,
            new AuditTargetData('onboarding_job', $jobId),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => 'website_approval',
                'target_label' => 'approval='.$approvalId.';'.$label,
            ],
        ));
    }

    private function identifier(string $left, string $right): string
    {
        $hex = substr(hash('sha256', $left.':'.$right), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
