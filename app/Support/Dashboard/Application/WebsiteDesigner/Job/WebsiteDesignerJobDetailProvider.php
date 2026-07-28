<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Job;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;

final readonly class WebsiteDesignerJobDetailProvider
{
    private const array PROGRESS = [
        'awaiting_inputs' => 20,
        'assigned' => 35,
        'in_progress' => 50,
        'blocked' => 50,
        'correction_required' => 70,
        'in_review' => 80,
        'ready_for_launch' => 100,
    ];

    public function __construct(private WebsiteDesignerDashboardReadInterface $jobs) {}

    public function provide(
        AuthorizationContext $context,
        string $jobId,
    ): ?DashboardSectionProjection {
        $job = $this->jobs->detail($context->identityId, $jobId);

        return $job === null
            ? null
            : new DashboardSectionProjection('job', $this->project($job));
    }

    /** @return array<string, mixed> */
    private function project(WebsiteDesignerJobDetailData $job): array
    {
        return [
            'id' => $job->onboardingJobId,
            'tenantId' => $job->tenantId,
            'websiteId' => $job->websiteId,
            'status' => $job->status,
            'statusLabel' => $this->label($job->status),
            'progress' => [
                'value' => self::PROGRESS[$job->status] ?? 0,
                'label' => (self::PROGRESS[$job->status] ?? 0).'% complete',
            ],
            'stages' => [
                $this->stage('content-collection', 'Content collection', $job->status, ['awaiting_inputs']),
                $this->stage('website-setup', 'Website setup', $job->status, ['assigned', 'in_progress', 'blocked', 'reopened']),
                $this->stage('review', 'Review status', $job->status, ['in_review', 'correction_required']),
                $this->stage('publish-readiness', 'Publish readiness', $job->status, ['ready_for_launch']),
            ],
            'timeline' => $this->timeline($job),
            'actions' => [
                [
                    'key' => 'back-to-queue',
                    'label' => 'Back to onboarding queue',
                    'description' => 'Return to your assigned jobs.',
                    'href' => route('dashboard.onboarding'),
                    'available' => true,
                ],
                [
                    'key' => 'website-setup',
                    'label' => 'Website setup',
                    'description' => 'Update the assigned clinic Website configuration below.',
                    'href' => '#website-setup',
                    'available' => true,
                ],
            ],
            'assignedAtLabel' => $job->assignedAt->format('j M Y, g:i A'),
            'updatedAtLabel' => $job->updatedAt->format('j M Y, g:i A'),
        ];
    }

    /**
     * @param  list<string>  $current
     * @return array{key: string, label: string, state: string, stateLabel: string}
     */
    private function stage(string $key, string $label, string $status, array $current): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'state' => in_array($status, $current, true) ? 'current' : 'not_current',
            'stateLabel' => in_array($status, $current, true) ? 'Current' : 'Not current',
        ];
    }

    /** @return list<array<string, string>> */
    private function timeline(WebsiteDesignerJobDetailData $job): array
    {
        $events = [];
        foreach ($job->lifecycle as $key => $occurredAt) {
            if ($occurredAt === null) {
                continue;
            }
            $events[] = [
                'key' => $key,
                'title' => $this->label(str_replace('_at', '', $key)),
                'occurredAt' => $occurredAt->format(DATE_ATOM),
                'occurredAtLabel' => $occurredAt->format('j M Y, g:i A'),
            ];
        }

        return $events;
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
