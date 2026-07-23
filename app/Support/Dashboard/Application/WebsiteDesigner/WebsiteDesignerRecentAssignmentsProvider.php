<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteDesignerRecentAssignmentsProvider implements DashboardSectionProviderInterface
{
    public function __construct(private WebsiteDesignerDashboardReadInterface $dashboard) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $data = $this->dashboard->forPlatformIdentity($context->identityId);

        return new DashboardSectionProjection('recentAssignments', array_map(
            static fn ($assignment): array => [
                'key' => $assignment->assignmentId,
                'title' => 'Onboarding job '.$assignment->onboardingJobId,
                'description' => self::statusLabel($assignment->status),
                'occurredAt' => $assignment->assignedAt->format(DATE_ATOM),
                'occurredAtLabel' => $assignment->assignedAt->format('j M Y, g:i A'),
            ],
            $data->recentAssignments,
        ));
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_inputs' => 'Pending content collection',
            'assigned', 'in_progress', 'blocked', 'reopened' => 'Website setup',
            'in_review', 'correction_required' => 'Review & revision',
            'ready_for_launch' => 'Ready to publish',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Assigned',
        };
    }
}
