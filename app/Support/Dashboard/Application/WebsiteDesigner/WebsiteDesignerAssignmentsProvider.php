<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteDesignerAssignmentsProvider implements DashboardSectionProviderInterface
{
    public function __construct(private WebsiteDesignerDashboardReadInterface $dashboard) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $data = $this->dashboard->forPlatformIdentity($context->identityId);

        return new DashboardSectionProjection('summaries', [
            $this->summary('assigned-jobs', 'Assigned onboarding jobs', $data->assignedJobs, 'Active projects assigned to you.'),
            $this->summary('pending-content', 'Pending content collection', $data->pendingContentCollection, 'Projects waiting for clinic content.'),
            $this->summary('website-setup', 'Website setup', $data->websiteSetup, 'Projects currently being prepared.'),
            $this->summary('review-revision', 'Review & revision', $data->reviewAndRevision, 'Projects in review or correction.'),
            $this->summary('ready-publish', 'Ready to publish', $data->readyToPublish, 'Projects ready for the publishing step.'),
            $this->summary('completed-projects', 'Completed projects', $data->completedProjects, 'Assignments completed successfully.'),
        ]);
    }

    /** @return array{key: string, label: string, value: string, detail: string, tone: string} */
    private function summary(string $key, string $label, int $value, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => (string) $value,
            'detail' => $detail,
            'tone' => $value > 0 ? 'positive' : 'neutral',
        ];
    }
}
