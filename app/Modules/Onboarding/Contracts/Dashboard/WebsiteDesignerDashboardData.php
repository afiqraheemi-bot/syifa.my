<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

final readonly class WebsiteDesignerDashboardData
{
    /**
     * @param  list<WebsiteDesignerRecentAssignmentData>  $recentAssignments
     */
    public function __construct(
        public int $assignedJobs,
        public int $pendingContentCollection,
        public int $websiteSetup,
        public int $reviewAndRevision,
        public int $readyToPublish,
        public int $completedProjects,
        public array $recentAssignments,
    ) {}
}
