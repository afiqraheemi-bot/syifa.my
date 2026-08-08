<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Queue;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;

final readonly class WebsiteDesignerQueueProvider
{
    public function __construct(
        private WebsiteDesignerDashboardReadInterface $jobs,
        private WebsiteReadInterface $websites,
        private WebsitePublicAddressReadInterface $addresses,
    ) {}

    public function provide(
        AuthorizationContext $context,
        WebsiteDesignerQueueCriteria $criteria,
    ): DashboardSectionProjection {
        $rows = $this->jobs->queue(
            $context->identityId,
            $criteria->status,
            $criteria->cursor,
            $criteria->perPage + 1,
            $criteria->search,
        );
        $hasMore = count($rows) > $criteria->perPage;
        $visible = array_slice($rows, 0, $criteria->perPage);
        $last = $visible === [] ? null : $visible[array_key_last($visible)];
        $nextCursor = $hasMore && $last instanceof WebsiteDesignerQueueJobData
            ? $last->assignmentId
            : null;

        return new DashboardSectionProjection('onboardingQueue', [
            'items' => array_map($this->project(...), $visible),
            'search' => [
                'action' => route('dashboard.onboarding'),
                'value' => $criteria->search,
                'placeholder' => 'Search job, tenant or Website reference',
            ],
            'statusFilter' => [
                'value' => $criteria->status,
                'options' => WebsiteDesignerQueueCriteria::statusOptions(),
            ],
            'pagination' => [
                'nextHref' => $nextCursor === null ? null : route('dashboard.onboarding', array_filter([
                    'search' => $criteria->search,
                    'status' => $criteria->status,
                    'cursor' => $nextCursor,
                    'per_page' => $criteria->perPage,
                ], static fn (string|int|null $value): bool => $value !== null)),
                'hasMore' => $hasMore,
                'perPage' => $criteria->perPage,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function project(WebsiteDesignerQueueJobData $job): array
    {
        $website = $this->websites->summary($job->tenantId);
        $address = $this->addresses->forWebsite($job->tenantId, $job->websiteId);

        return [
            'id' => $job->onboardingJobId,
            'clinicName' => $website !== null && $website->id === $job->websiteId
                ? $website->clinicName
                : 'Clinic',
            'publicHost' => $address?->host,
            'publicUrl' => $address?->url,
            'tenantId' => $job->tenantId,
            'websiteId' => $job->websiteId,
            'jobReference' => $this->reference('JOB', $job->onboardingJobId),
            'tenantReference' => $this->reference('TENANT', $job->tenantId),
            'websiteReference' => $this->reference('WEB', $job->websiteId),
            'status' => $job->status,
            'statusLabel' => ucwords(str_replace('_', ' ', $job->status)),
            'contentCollection' => $this->stage($job->status, ['awaiting_inputs']),
            'websiteSetup' => $this->stage($job->status, ['assigned', 'in_progress', 'blocked', 'reopened']),
            'review' => $this->stage($job->status, ['in_review', 'correction_required']),
            'publishReadiness' => $this->stage($job->status, ['ready_for_launch']),
            'assignedAt' => $job->assignedAt->format(DATE_ATOM),
            'assignedAtLabel' => $job->assignedAt->format('j M Y'),
            'updatedAt' => $job->updatedAt->format(DATE_ATOM),
            'updatedAtLabel' => $job->updatedAt->format('j M Y, g:i A'),
            'detailHref' => route('dashboard.onboarding.show', ['jobId' => $job->onboardingJobId]),
        ];
    }

    private function reference(string $prefix, string $value): string
    {
        return $prefix.'-'.strtoupper(substr($value, 0, 8));
    }

    /** @param list<string> $activeStatuses */
    private function stage(string $status, array $activeStatuses): string
    {
        return in_array($status, $activeStatuses, true) ? 'Current' : 'Not current';
    }
}
